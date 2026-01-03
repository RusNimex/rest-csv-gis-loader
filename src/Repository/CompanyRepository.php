<?php 

namespace App\Repository;

use App\Interfaces\RepositoryInterface;

/**
 * Репозиторий для работы с компаниями
 */
class CompanyRepository extends BaseRepository implements RepositoryInterface
{
  /**
     * Вставка данных в таблицу
     */
    public function insert(array $rows): int
    {
        return count($rows);
    }
}

