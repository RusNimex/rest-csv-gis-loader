<?php

namespace App\Helpers;

trait FormatterTrait
{

    /**
     * Форматирует байты в читаемый формат
     *
     * @param int $bytes Количество байт
     * @return string Отформатированная строка
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Форматирует длительность выполнения в читаемый формат
     *
     * @param float $seconds Количество секунд
     * @return string Отформатированная строка
     */
    protected function formatDuration(float $seconds): string
    {
        if ($seconds < 1) {
            return round($seconds * 1000, 2) . ' мс';
        }

        if ($seconds < 60) {
            return round($seconds, 2) . ' сек';
        }

        $minutes = intval(floor($seconds / 60));
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $minutes . ' мин ' . round($remainingSeconds, 2) . ' сек';
        }

        $hours = intval(floor($minutes / 60));
        $remainingMinutes = $minutes % 60;

        return $hours . ' ч ' . $remainingMinutes . ' мин ' . round($remainingSeconds, 2) . ' сек';
    }
}

