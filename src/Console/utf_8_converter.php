<?php
/**
 * Скрипт для конвертации всех CSV файлов в UTF-8
 * 
 * Использование: php utf_8_converter.php
 */

$sourceDir = '/tmp/rubrics_utf8';
$processedCount = 0;
$errorCount = 0;
$skippedCount = 0;

/**
 * Строгая проверка, является ли строка валидным UTF-8
 */
function isValidUtf8(string $string): bool
{
    if (empty($string)) {
        return true;
    }
    
    // Основная проверка валидности UTF-8
    if (!mb_check_encoding($string, 'UTF-8')) {
        return false;
    }
    
    // Дополнительная проверка: пробуем определить кодировку
    // Если строка валидна как UTF-8, mb_detect_encoding должен это определить
    $detected = mb_detect_encoding($string, ['UTF-8', 'Windows-1251', 'ISO-8859-1', 'CP866'], true);
    
    // Если определили как UTF-8 - точно UTF-8
    if ($detected === 'UTF-8') {
        return true;
    }
    
    // Если не определили, но mb_check_encoding вернул true - тоже считаем UTF-8
    // (mb_detect_encoding может ошибаться, но mb_check_encoding более надежен)
    return true;
}

/**
 * Рекурсивно обрабатывает все CSV файлы в директории
 */
function processDirectory(string $dir, int &$processed, int &$errors, int &$skipped): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'csv') {
            $result = convertFileToUtf8($file->getPathname());
            
            if ($result === 'converted') {
                $processed++;
                echo "✓ Конвертирован: {$file->getPathname()}\n";
            } elseif ($result === 'skipped') {
                $skipped++;
                echo "- Пропущен (уже UTF-8): {$file->getPathname()}\n";
            } else {
                $errors++;
                echo "✗ Ошибка: {$file->getPathname()} - {$result}\n";
                
                // Дополнительная диагностика для файлов с ошибками
                if (strpos($result, 'не является валидным UTF-8') !== false) {
                    $handle = fopen($file->getPathname(), 'rb');
                    if ($handle !== false) {
                        $sample = fread($handle, 1024);
                        fclose($handle);
                        
                        $detected = mb_detect_encoding($sample, ['UTF-8', 'Windows-1251', 'ISO-8859-1', 'CP866'], true);
                        $isValid = mb_check_encoding($sample, 'UTF-8');
                        echo "   Диагностика: определена кодировка: " . ($detected ?: 'неизвестная') . 
                             ", валиден UTF-8: " . ($isValid ? 'да' : 'нет') . "\n";
                    }
                }
            }
        }
    }
}

/**
 * Конвертирует файл в UTF-8
 * 
 * @param string $filePath Путь к файлу
 * @return string 'converted' | 'skipped' | сообщение об ошибке
 */
function convertFileToUtf8(string $filePath): string
{
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return "Файл недоступен";
    }

    // Читаем первые 50 КБ для более точного определения кодировки
    $handle = fopen($filePath, 'rb');
    if ($handle === false) {
        return "Не удалось открыть файл";
    }

    // Проверяем BOM
    $bom = fread($handle, 3);
    $hasBom = ($bom === "\xEF\xBB\xBF");
    
    $sampleSize = min(50 * 1024, filesize($filePath));
    $sample = $hasBom ? fread($handle, $sampleSize - 3) : ($bom . fread($handle, $sampleSize - 3));
    fclose($handle);

    // ПЕРВАЯ проверка: если строка валидна как UTF-8, не конвертируем
    if (mb_check_encoding($sample, 'UTF-8')) {
        // Дополнительная проверка через mb_detect_encoding
        $detected = mb_detect_encoding($sample, ['UTF-8', 'Windows-1251', 'ISO-8859-1', 'CP866'], true);
        
        // Если определили как UTF-8 - точно пропускаем
        if ($detected === 'UTF-8') {
            if ($hasBom) {
                return removeBomFromFile($filePath);
            }
            return 'skipped';
        }
        
        // Если mb_check_encoding говорит что UTF-8, но mb_detect_encoding не определил
        // Это может быть из-за особенностей файла, но файл валиден
        // Пропускаем, чтобы не сломать уже конвертированные файлы
        if ($hasBom) {
            return removeBomFromFile($filePath);
        }
        return 'skipped';
    }

    // ДОПОЛНИТЕЛЬНАЯ проверка: читаем больше данных для более точной проверки
    // Иногда выборка 50 КБ может не показать полную картину
    $largerSampleHandle = fopen($filePath, 'rb');
    if ($largerSampleHandle !== false) {
        $largerSampleSize = min(200 * 1024, filesize($filePath)); // 200 КБ для более точной проверки
        if ($hasBom) {
            fread($largerSampleHandle, 3);
        }
        $largerSample = fread($largerSampleHandle, $largerSampleSize);
        fclose($largerSampleHandle);
        
        // Если большая выборка валидна как UTF-8 - файл уже в UTF-8
        if (mb_check_encoding($largerSample, 'UTF-8')) {
            // Проверяем через mb_detect_encoding еще раз
            $largerDetected = mb_detect_encoding($largerSample, ['UTF-8', 'Windows-1251', 'ISO-8859-1', 'CP866'], true);
            if ($largerDetected === 'UTF-8' || $largerDetected === false) {
                // Файл валиден как UTF-8, пропускаем
                if ($hasBom) {
                    return removeBomFromFile($filePath);
                }
                return 'skipped';
            }
        }
    }

    // Определяем кодировку только если НЕ UTF-8
    // Исключаем UTF-8 из списка для более точного определения
    $encoding = mb_detect_encoding($sample, ['Windows-1251', 'ISO-8859-1', 'CP866', 'KOI8-R'], true);
    
    if ($encoding === false) {
        // Не удалось определить - пропускаем, чтобы не сломать
        return 'skipped';
    }

    // Конвертируем файл по частям (оптимизированно для больших файлов)
    $tempPath = $filePath . '.tmp';
    $sourceHandle = fopen($filePath, 'rb');
    $targetHandle = fopen($tempPath, 'wb');
    
    if ($sourceHandle === false || $targetHandle === false) {
        if ($sourceHandle !== false) fclose($sourceHandle);
        if ($targetHandle !== false) fclose($targetHandle);
        @unlink($tempPath);
        return "Не удалось создать временный файл";
    }

    // Пропускаем BOM если есть
    if ($hasBom) {
        fread($sourceHandle, 3);
    }

    // Конвертируем по частям (по 1 МБ за раз)
    $chunkSize = 1024 * 1024;
    while (!feof($sourceHandle)) {
        $chunk = fread($sourceHandle, $chunkSize);
        if ($chunk === false || $chunk === '') {
            break;
        }
        
        $converted = mb_convert_encoding($chunk, 'UTF-8', $encoding);
        if ($converted === false) {
            fclose($sourceHandle);
            fclose($targetHandle);
            @unlink($tempPath);
            return "Ошибка конвертации";
        }
        
        fwrite($targetHandle, $converted);
    }

    fclose($sourceHandle);
    fclose($targetHandle);

    // Проверяем результат конвертации
    $checkHandle = fopen($tempPath, 'rb');
    if ($checkHandle !== false) {
        $checkSample = fread($checkHandle, min(10240, filesize($tempPath))); // Увеличиваем проверку до 10 КБ
        fclose($checkHandle);
        
        // Используем более простую проверку - только mb_check_encoding
        if (!mb_check_encoding($checkSample, 'UTF-8')) {
            // Если результат невалидный, возможно файл уже был в UTF-8
            // Проверяем оригинальный файл еще раз (читаем больше данных)
            $originalHandle = fopen($filePath, 'rb');
            if ($originalHandle !== false) {
                if ($hasBom) {
                    fread($originalHandle, 3);
                }
                $originalSample = fread($originalHandle, min(200 * 1024, filesize($filePath))); // 200 КБ для точности
                fclose($originalHandle);
                
                // Если оригинал валиден как UTF-8, значит была ошибка определения кодировки
                if (mb_check_encoding($originalSample, 'UTF-8')) {
                    $originalDetected = mb_detect_encoding($originalSample, ['UTF-8', 'Windows-1251', 'ISO-8859-1', 'CP866'], true);
                    // Если определили как UTF-8 или не определили (но валиден) - пропускаем
                    if ($originalDetected === 'UTF-8' || ($originalDetected === false && mb_check_encoding($originalSample, 'UTF-8'))) {
                        @unlink($tempPath);
                        return 'skipped'; // Файл уже был в UTF-8
                    }
                }
            }
            
            // Если оригинал не валиден UTF-8, но конвертация дала невалидный результат
            // Это может быть из-за смешанной кодировки или битых данных
            // В таком случае пропускаем файл, чтобы не сломать
            @unlink($tempPath);
            return 'skipped'; // Пропускаем проблемный файл
        }
    }

    // Заменяем оригинальный файл
    if (!rename($tempPath, $filePath)) {
        @unlink($tempPath);
        return "Не удалось заменить файл";
    }

    return 'converted';
}

/**
 * Удаляет BOM из файла
 */
function removeBomFromFile(string $filePath): string
{
    $handle = fopen($filePath, 'rb');
    if ($handle === false) {
        return "Не удалось открыть файл";
    }

    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        fclose($handle);
        return 'skipped';
    }

    $tempPath = $filePath . '.tmp';
    $targetHandle = fopen($tempPath, 'wb');
    if ($targetHandle === false) {
        fclose($handle);
        return "Не удалось создать временный файл";
    }

    // Копируем остальное содержимое
    while (!feof($handle)) {
        $chunk = fread($handle, 8192);
        if ($chunk !== false && $chunk !== '') {
            fwrite($targetHandle, $chunk);
        }
    }

    fclose($handle);
    fclose($targetHandle);

    if (!rename($tempPath, $filePath)) {
        @unlink($tempPath);
        return "Не удалось заменить файл";
    }

    return 'converted';
}

// Запускаем обработку
echo "Начало конвертации CSV файлов в UTF-8...\n";
echo "Папка: {$sourceDir}\n\n";

$startTime = microtime(true);
processDirectory($sourceDir, $processedCount, $errorCount, $skippedCount);
$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n";
echo "═══════════════════════════════════════\n";
echo "Результаты:\n";
echo "  Конвертировано: {$processedCount}\n";
echo "  Пропущено (уже UTF-8): {$skippedCount}\n";
echo "  Ошибок: {$errorCount}\n";
echo "  Время выполнения: {$duration} сек\n";
echo "═══════════════════════════════════════\n";

