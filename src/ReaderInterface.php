<?php
namespace Onlineconf;

interface ReaderInterface 
{
    /**
     * Gets value or returns default value passed as 2nd argument
     * @param string      $key
     * @param string|null $default
     * @return null|string|int return null if not found, else - mixed value
     */
    public function get(string $key, ?string $default = null);

    /**
     * Returns list of key in the branch
     * @param string $branch
     * @return array
     */
    public function getList(string $branch = ''): array;
}
