<?php 

namespace App\Repository;

use App\Interfaces\RepositoryInterface;
use App\Models\GisCompany;

/**
 * Репозиторий для работы с компаниями
 */
class CompanyRepository extends BaseRepository implements RepositoryInterface
{
    /** @var array кэш регионов */
    protected array $region = [];

    /** @var array кэш районов */
    protected array $district = [];

    /** @var array кэш городов */
    protected array $city = [];

    /** @var array кэш категорий */
    protected array $category = [];

    /** @var array кэш подкатегорий */
    protected array $subcategory = [];

    /** @var array кэш компаний */
    protected array $company = [];

    /** @var array соберем битые категории/подкатегории */
    protected array $sanitized = [];

    /** @var array для массовой вставки Компания-Гео одним запросом */
    protected array $companyGeos = [];

    /** @var array для массовой вставки Компания-Категории/Подкатегории одним запросом */
    protected array $companyCategories = [];

    /** @var int кол-во компаний для статистики */
    protected int $companyCount = 0;

    /**
     * Вставка данных в таблицу в определенном порядке
     */
    public function insert(array $rows): int
    {
        $companies = 0;

        foreach ($rows as $row) {
            if (!($geoId = $this->insertGeo($row))) {
                continue;
            }

            [$categories, $subcategories] = $this->insertCategory($row);

            if (!($companyId = $this->insertCompany($row))) {
                continue;
            }

            $this->collectCompanyGeos($companyId, $geoId);
            $this->collectCompanyCategories($companyId, $categories, $subcategories);
            $companies++;
        }
        $this->insertCompanyGeos();
        $this->insertCompanyCategories();

        return $companies;
    }

    /**
     * Вставка данных в таблицу geo
     *
     * @param GisCompany $record
     * @return null|int
     */
    private function insertGeo(GisCompany $record): ?int
    {
        $regionId = $districtId = $cityId = null;
        $tables = ['region', 'district', 'city'];
        foreach ($tables as $table) {
            try {
                if (!$record->$table) {
                    continue;
                }
                if (!isset($this->{$table}[$record->$table])) {
                    $this->pdo->beginTransaction();
                    $stmt = $this->pdo->prepare(
                        "INSERT INTO csv.{$table} (name) VALUES (?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
                    );
                    $stmt->execute([$record->$table]);
                    $this->{$table}[$record->$table] = (int)$this->pdo->lastInsertId();
                    
                    $this->pdo->commit();
                }
                ${$table . 'Id'} = $this->{$table}[$record->$table];
            } catch (\Exception $e) {
                $this->pdo->rollBack();
                $this->errors[] = sprintf('%s - %s: %s', $table, $record->$table, $e->getMessage());
            }
        }

        if (empty($regionId) && empty($districtId) && empty($cityId)) {
            return null;
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                'INSERT INTO csv.geo (region_id, district_id, city_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $stmt->execute([$regionId, $districtId, $cityId]);
            $geoId = (int)$this->pdo->lastInsertId();

            $this->pdo->commit();

        } catch (\PDOException $e) {
            $this->pdo->rollback();
            $this->errors[] = sprintf('%s: %s', $record->region, $e->getMessage());

            return null;
        }

        return $geoId;
    }

    /**
     * Вставка данных по категории и подкатегории
     *
     * @param GisCompany $record
     * @return array<array<int>, array<int>>|null
     */
    private function insertCategory(GisCompany $record): ?array
    {
        $fields = ['category', 'subcategory'];
        $categoryIds = $subcategoryIds = null;

        foreach ($fields as $field) {
            ${$field} = $this->extractCategories($record->{$field});
            foreach (${$field} as $type) {
                if (!isset($this->{$field}[$type])) {
                    $this->pdo->beginTransaction();
                    $stmt = $this->pdo->prepare(
                        "INSERT INTO csv.{$field} (name) VALUES (?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
                    );
                    $stmt->execute([$type]);
                    $this->{$field}[$type] = (int)$this->pdo->lastInsertId();

                    $this->pdo->commit();

                }
                ${$field . 'Ids'}[] = $this->{$field}[$type];
            }
        }

        return [$categoryIds, $subcategoryIds];
    }

    /**
     * Добавляем компанию и связываем ее с категорией и гео
     *
     * @param GisCompany $record
     * @return null|int
     */
    private function insertCompany(GisCompany $record): ?int
    {
        if (isset($this->company[$record->name])) {
            return $this->company[$record->name];
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                "INSERT INTO csv.company (name) VALUES (?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
            );
            $stmt->execute([$record->name]);
            $companyId = (int)$this->pdo->lastInsertId();

            $this->pdo->commit();

            $this->company[$record->name] = $companyId;
            $this->companyCount++;

            return $companyId;
        } catch (\PDOException $e) {
            $this->pdo->rollback();
            $this->errors[] = sprintf('%s: %s', $record->name, $e->getMessage());

            return null;
        }
    }

    /**
     * Выделяем категории/подкатегории из строки разделенные запятой
     *
     * @param string $commaValues
     * @return array
     */
    private function extractCategories(string $commaValues): array
    {
        $values = explode(',', $commaValues);
        $sanitized = array_map('trim', $values);
        $lastValue = $sanitized[count($sanitized) - 1];
        if (empty($lastValue) || !is_string($lastValue) || strlen($lastValue) < 5) {
            unset($sanitized[count($sanitized) - 1]);
            $this->sanitized[] = $lastValue;
        }

        return $sanitized;
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
     * Вставим одним запросом привязку компаний к гео
     *
     * @return void
     */
    private function insertCompanyGeos(): void
    {
        if (empty($this->companyGeos)) {
            return;
        }

        $sql = 'INSERT IGNORE INTO csv.company_geo (company_id, geo_id) VALUES ';
        $values = [];
        $params = [];

        foreach ($this->companyGeos as $companyId => $geoIds) {
            foreach ($geoIds as $geoId) {
                $values[] = '(?, ?)';
                $params[] = $companyId;
                $params[] = $geoId;
            }
        }
        $this->insertPivot($values, $sql, $params);
    }

    /**
     * Вставим одним запросом привязку компаний к категориям и подкатегориям
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
            $sql = "INSERT IGNORE INTO csv.company_{$type} (company_id, {$type}_id) VALUES ";
            $values = [];
            $params = [];
            foreach ($this->companyCategories as $companyId => $types) {
                foreach ($types[$type] as $valueId) {
                    $values[] = '(?, ?)';
                    $params[] = $companyId;
                    $params[] = $valueId;
                }
            }
            $this->insertPivot($values, $sql, $params);
        }
    }

    /**
     * Когда известна компания и ее гео данные, то остальное вставляем
     * массово одним запросом вставка связки компаний с доп данными
     *
     * @param array $values
     * @param string $sql
     * @param array $params
     * @return void
     */
    private function insertPivot(array $values, string $sql, array $params): void
    {
        $sql .= implode(', ', $values);

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $this->pdo->commit();

        } catch (\PDOException $e) {
            $this->pdo->rollback();
            $this->errors[] = $e->getMessage();
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
}

