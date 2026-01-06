<?php 

namespace App\Repository;

use App\Interfaces\RepositoryInterface;
use App\Models\GisCompany;

/**
 * Репозиторий для загрузки CSV-данных в базу
 */
class CompanyRepository extends BaseRepository implements RepositoryInterface
{
    /** @var int Максимальное количество записей в одном батче для pivot таблиц */
    private const PIVOT_BATCH_SIZE = 5000;

    /**
     * Вставка данных в таблицу в определенном порядке
     *
     * 1 Временно отключаем проверку внешних ключей для ускорения импорта
     * 2 Предзагрузка всех справочников батчем
     * 3 Батч-вставка geo записей
     * 4 Батч-вставка компаний
     * 5 Обработка связей
     * 6 Массовая вставка связей
     * 7 Включаем обратно проверку внешних ключей
     */
    public function insert(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        try {
            $this->pdo->beginTransaction();

            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            $this->preloadDictionaries($rows);
            $this->batchInsertGeo($rows);
            $this->batchInsertCompanies($rows);

            // Обработка связей
            foreach ($rows as $row) {
                $geoId = $this->getGeoId($row);
                if (!$geoId) {
                    continue;
                }

                [$categoryIds, $subcategoryIds] = $this->getCategoryIds($row);

                $companyId = $this->company[$row->name] ?? null;
                if (!$companyId) {
                    continue;
                }

                $this->collectCompanyGeos($companyId, $geoId);
                $this->collectCompanyCategories($companyId, $categoryIds, $subcategoryIds);
            }

            // Массовая вставка связей
            $this->insertCompanyGeos();
            $this->insertCompanyCategories();

            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            $this->pdo->commit();
        } catch (\Exception $e) {
            try {
                $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            } catch (\Exception $fkException) {
                // Игнорируем ошибку при восстановлении FK проверки
            }
            $this->pdo->rollBack();
            $this->errors[] = sprintf('Ошибка при импорте: %s', $e->getMessage());
        }
    }

    /**
     * Получим статистику импорта
     *
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'company' => $this->companyCount,
            'category' => count($this->companyCategories),
            'region' => count($this->region),
            'district' => count($this->district),
            'city' => count($this->city),
            'errors' => $this->errors,
        ];
    }

    /**
     * Предзагрузка всех справочников батчем
     *
     * @param array $rows
     * @return void
     */
    private function preloadDictionaries(array $rows): void
    {
        $uniqueValues = [
            'region' => [],
            'district' => [],
            'city' => [],
            'category' => [],
            'subcategory' => []
        ];

        // Собираем уникальные значения из всех записей
        foreach ($rows as $row) {
            if (!empty($row->region)) {
                $uniqueValues['region'][$row->region] = true;
            }
            if (!empty($row->district)) {
                $uniqueValues['district'][$row->district] = true;
            }
            if (!empty($row->city)) {
                $uniqueValues['city'][$row->city] = true;
            }

            $categories = $this->extractCategories($row->category);
            foreach ($categories as $cat) {
                if (!empty($cat)) {
                    $uniqueValues['category'][$cat] = true;
                }
            }

            $subcategories = $this->extractCategories($row->subcategory);
            foreach ($subcategories as $subcategory) {
                if (!empty($subcategory)) {
                    $uniqueValues['subcategory'][$subcategory] = true;
                }
            }
        }

        // Батч-вставка для каждого справочника
        foreach ($uniqueValues as $table => $values) {
            if (!empty($values)) {
                $this->batchInsertDictionary($table, array_keys($values));
            }
        }
    }

    /**
     * Батч-вставка справочника (только новые значения)
     *
     * Фильтруем уже загруженные значения
     * Вставляем новые значения батчем
     * @param string $table
     * @param array $names
     * @return void
     */
    private function batchInsertDictionary(string $table, array $names): void
    {
        $newNames = [];
        foreach ($names as $name) {
            if (!isset($this->{$table}[$name])) {
                $newNames[] = $name;
            }
        }

        if (empty($newNames)) {
            return;
        }

        // Вставляем новые значения батчем
        $placeholders = implode(',', array_fill(0, count($newNames), '(?)'));
        $sql = "INSERT IGNORE INTO csv.{$table} (name) VALUES {$placeholders}";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($newNames);

            // Загружаем ID для всех значений одним запросом
            $this->loadDictionaryFromDb($table, $newNames);
        } catch (\PDOException $e) {
            $this->errors[] = sprintf('Ошибка при вставке %s: %s', $table, $e->getMessage());
        }
    }

    /**
     * Загрузка в кэш ID справочника из БД
     *
     * @param string $table
     * @param array $names
     * @return void
     */
    private function loadDictionaryFromDb(string $table, array $names): void
    {
        if (empty($names)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $sql = "SELECT id, name FROM csv.{$table} WHERE name IN ({$placeholders})";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($names);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $this->{$table}[$row['name']] = (int)$row['id'];
            }
        } catch (\PDOException $e) {
            $this->errors[] = sprintf('Ошибка при загрузке %s: %s', $table, $e->getMessage());
        }
    }

    /**
     * Батч-вставка geo записей
     *
     * @param array $rows
     * @return void
     */
    private function batchInsertGeo(array $rows): void
    {
        $geoData = [];
        foreach ($rows as $row) {
            $regionId = !empty($row->region) ? ($this->region[$row->region] ?? null) : null;
            $districtId = !empty($row->district) ? ($this->district[$row->district] ?? null) : null;
            $cityId = !empty($row->city) ? ($this->city[$row->city] ?? null) : null;

            if (empty($regionId) && empty($districtId) && empty($cityId)) {
                continue;
            }

            $key = "{$regionId}:{$districtId}:{$cityId}";
            if (!isset($geoData[$key])) {
                $geoData[$key] = [$regionId, $districtId, $cityId];
            }
        }

        if (empty($geoData)) {
            return;
        }

        // Вставляем уникальные комбинации geo
        $values = [];
        $params = [];
        foreach ($geoData as $geo) {
            $values[] = '(?, ?, ?)';
            $params[] = $geo[0];
            $params[] = $geo[1];
            $params[] = $geo[2];
        }

        $sql = "INSERT IGNORE INTO csv.geo (region_id, district_id, city_id) VALUES " 
             . implode(',', $values);

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            // Загружаем ID обратно в кэш одним запросом
            $this->loadGeoFromDb(array_keys($geoData));
        } catch (\PDOException $e) {
            $this->errors[] = sprintf('Ошибка при вставке geo: %s', $e->getMessage());
        }
    }

    /**
     * Загрузка geo ID из БД в кэш
     *
     * @param array $keys
     * @return void
     */
    private function loadGeoFromDb(array $keys): void
    {
        if (empty($keys)) {
            return;
        }

        $conditions = [];
        $params = [];
        foreach ($keys as $key) {
            [$regionId, $districtId, $cityId] = explode(':', $key);
            $conditions[] = '((region_id <=> ?) AND (district_id <=> ?) AND (city_id <=> ?))';
            $params[] = $regionId === '' ? null : (int)$regionId;
            $params[] = $districtId === '' ? null : (int)$districtId;
            $params[] = $cityId === '' ? null : (int)$cityId;
        }

        $sql = "SELECT id, region_id, district_id, city_id FROM csv.geo WHERE " 
             . implode(' OR ', $conditions);

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $key = sprintf(
                    "%s:%s:%s",
                    $row['region_id'] ?? '',
                    $row['district_id'] ?? '',
                    $row['city_id'] ?? ''
                );
                $this->geoCache[$key] = (int)$row['id'];
            }
        } catch (\PDOException $e) {
            $this->errors[] = sprintf('Ошибка при загрузке geo: %s', $e->getMessage());
        }
    }

    /**
     * Получение geo ID из кэша
     *
     * @param GisCompany $record
     * @return int|null
     */
    private function getGeoId(GisCompany $record): ?int
    {
        $regionId = !empty($record->region) ? ($this->region[$record->region] ?? null) : null;
        $districtId = !empty($record->district) ? ($this->district[$record->district] ?? null) : null;
        $cityId = !empty($record->city) ? ($this->city[$record->city] ?? null) : null;

        if (empty($regionId) && empty($districtId) && empty($cityId)) {
            return null;
        }

        $key = sprintf("%s:%s:%s", $regionId ?? '', $districtId ?? '', $cityId ?? '');

        return $this->geoCache[$key] ?? null;
    }

    /**
     * Получение ID категорий и подкатегорий из кэша
     *
     * @param GisCompany $record
     * @return array{0: array<int>, 1: array<int>}
     */
    private function getCategoryIds(GisCompany $record): array
    {
        $categoryIds = [];
        $subcategoryIds = [];

        $categories = $this->extractCategories($record->category);
        foreach ($categories as $category) {
            if (!empty($category) && isset($this->category[$category])) {
                $categoryIds[] = $this->category[$category];
            }
        }

        $subcategories = $this->extractCategories($record->subcategory);
        foreach ($subcategories as $subcategory) {
            if (!empty($subcategory) && isset($this->subcategory[$subcategory])) {
                $subcategoryIds[] = $this->subcategory[$subcategory];
            }
        }

        return [$categoryIds, $subcategoryIds];
    }

    /**
     * Батч-вставка компаний,
     * чтоб все компании имели ID и были загружены в кеш
     *
     * @param array $rows
     * @return void
     */
    private function batchInsertCompanies(array $rows): void
    {
        $uniqueCompanies = [];
        foreach ($rows as $row) {
            if (!empty($row->name) && !isset($this->company[$row->name])) {
                $uniqueCompanies[$row->name] = true;
            }
        }

        if (empty($uniqueCompanies)) {
            return;
        }

        $companyNames = array_keys($uniqueCompanies);
        $placeholders = implode(',', array_fill(0, count($companyNames), '(?)'));
        $sql = "INSERT IGNORE INTO csv.company (name) VALUES {$placeholders}";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($companyNames);

            $this->loadCompaniesFromDb($companyNames);
        } catch (\PDOException $e) {
            $this->errors[] = sprintf('Ошибка при вставке компаний: %s', $e->getMessage());
        }
    }

    /**
     * Загрузка ID компаний из БД
     *
     * @param array $names
     * @return void
     */
    private function loadCompaniesFromDb(array $names): void
    {
        if (empty($names)) {
            return;
        }

        // Фильтруем уже загруженные
        $newNames = [];
        foreach ($names as $name) {
            if (!empty($name) && !isset($this->company[$name])) {
                $newNames[] = $name;
            }
        }

        if (empty($newNames)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($newNames), '?'));
        $sql = "SELECT id, name FROM csv.company WHERE name IN ({$placeholders})";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($newNames);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (!isset($this->company[$row['name']])) {
                    $this->company[$row['name']] = (int)$row['id'];
                    $this->companyCount++;
                }
            }
        } catch (\PDOException $e) {
            $this->errors[] = sprintf('Ошибка при загрузке компаний: %s', $e->getMessage());
        }
    }

    /**
     * Выделяем категории/подкатегории из строки разделенные запятой
     *
     * Дополнительно: удаляем последний элемент, если общая длина строки подозрительно большая
     * @param string $commaValues
     * @return array
     */
    private function extractCategories(string $commaValues): array
    {
        $values = explode(',', $commaValues);
        $sanitized = array_map('trim', $values);
    
        if (empty($sanitized)) {
            return [];
        }
    
        // Если строка длинная или много элементов - удаляем последний
        $strLength = mb_strlen($commaValues, 'UTF-8');
        $elementCount = count($sanitized);
        
        if ($elementCount > 1) {
            $lastValue = $sanitized[$elementCount - 1];
            $lastLength = mb_strlen($lastValue, 'UTF-8');
            
            // Удаляем последний элемент если:
            // 1. Строка очень длинная (>= 540 символов), есть вероятность обрезки названия категории
            // 2. ИЛИ последний элемент подозрительно короткий (< 4 символов)
            // 3. ИЛИ последний элемент заканчивается на "/" или "-"
            $shouldRemove = false;
            
            if ($strLength >= 540) {
                $shouldRemove = true;
            } elseif ($lastLength < 4 && $elementCount > 2) {
                $shouldRemove = true;
            } elseif (in_array(mb_substr(rtrim($lastValue), -1, 1, 'UTF-8'), ['/', '-', ','])) {
                $shouldRemove = true;
            }
            
            if ($shouldRemove) {
                if (!isset($this->errors['category_broken'])) {
                    $this->errors['category_broken'] = [];
                }
                $this->errors['category_broken'][] = array_pop($sanitized);
            }
        }
    
        return array_values($sanitized);
    }

    /**
     * Привяжем компанию к гео
     *
     * @param int $companyId
     * @param int $geoId
     * @return void
     */
    private function collectCompanyGeos(int $companyId, int $geoId): void
    {
        if (!isset($this->companyGeos[$companyId])) {
            $this->companyGeos[$companyId] = [];
        }

        if (!in_array($geoId, $this->companyGeos[$companyId])) {
            $this->companyGeos[$companyId][] = $geoId;
        }
    }

    /**
     * Привяжем компанию к категориям и подкатегориям
     *
     * @param int $companyId
     * @param array $category
     * @param array $subcategory
     * @return void
     */
    private function collectCompanyCategories(int $companyId, array $category, array $subcategory): void
    {
        if (!isset($this->companyCategories[$companyId])) {
            $this->companyCategories[$companyId] = [];
        }

        $fields = ['category', 'subcategory'];
        foreach ($fields as $field) {
            if (!isset($this->companyCategories[$companyId][$field])) {
                $this->companyCategories[$companyId][$field] = [];
            }
            $this->companyCategories[$companyId][$field] = array_unique(
                array_merge(${$field}, $this->companyCategories[$companyId][$field])
            );
        }
    }

    /**
     * Вставим батчами привязку компаний к гео
     *
     * @return void
     */
    private function insertCompanyGeos(): void
    {
        if (empty($this->companyGeos)) {
            return;
        }

        // Собираем все связи в плоский массив
        $allLinks = [];
        foreach ($this->companyGeos as $companyId => $geoIds) {
            foreach ($geoIds as $geoId) {
                $allLinks[] = [$companyId, $geoId];
            }
        }

        // Разбиваем на батчи
        $batches = array_chunk($allLinks, self::PIVOT_BATCH_SIZE);
        
        foreach ($batches as $batch) {
            $sql = 'INSERT IGNORE INTO csv.company_geo (company_id, geo_id) VALUES ';
            $values = [];
            $params = [];

            foreach ($batch as $link) {
                $values[] = '(?, ?)';
                $params[] = $link[0];
                $params[] = $link[1];
            }

            $this->insertPivot($values, $sql, $params);
        }
    }

    /**
     * Вставим батчами привязку компаний к категориям и подкатегориям
     *
     * @return void
     */
    private function insertCompanyCategories(): void
    {
        if (empty($this->companyCategories)) {
            return;
        }

        $fields = ['category', 'subcategory'];

        foreach ($fields as $type) {
            // Собираем все связи в плоский массив
            $allLinks = [];
            foreach ($this->companyCategories as $companyId => $types) {
                foreach ($types[$type] as $valueId) {
                    $allLinks[] = [$companyId, $valueId];
                }
            }

            if (empty($allLinks)) {
                continue;
            }

            // Разбиваем на батчи
            $batches = array_chunk($allLinks, self::PIVOT_BATCH_SIZE);

            foreach ($batches as $batch) {
                $sql = "INSERT IGNORE INTO csv.company_{$type} (company_id, {$type}_id) VALUES ";
                $values = [];
                $params = [];

                foreach ($batch as $link) {
                    $values[] = '(?, ?)';
                    $params[] = $link[0];
                    $params[] = $link[1];
                }

                $this->insertPivot($values, $sql, $params);
            }
        }
    }

    /**
     * Заполняем таблицы со связями
     *
     * @param array $values
     * @param string $sql
     * @param array $params
     * @return void
     */
    private function insertPivot(array $values, string $sql, array $params): void
    {
        if (empty($values)) {
            return;
        }

        $sql .= implode(', ', $values);

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            $this->errors[] = sprintf('Ошибка при вставке связей: %s', $e->getMessage());
        }
    }
}

