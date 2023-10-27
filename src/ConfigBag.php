<?php
declare(strict_types=1);

namespace Onlineconf;

class ConfigBag implements ConfigInterface
{
    private string $pathPrefix;
    private string $separator;
    private string $ocDelimiter;
    private ReaderInterface $client;
    private array $cache = [];

    /**
     * @param string               $pathPrefix  onlineconf params namespace/path for params
     * @param string               $appPathDelimiter   customizable path delimiter for your app
     * @param string               $ocDelimiter path delimiter specified in Onlineconf module export (usually "/")
     * @param ReaderInterface|null $client
     */
    public function __construct(
        string $pathPrefix = '',
        string $appPathDelimiter = '.',
        string $ocDelimiter = '/',
        ?ReaderInterface $client = null)
    {
        $this->pathPrefix = $pathPrefix;
        $this->separator = $appPathDelimiter;
        $this->ocDelimiter = $ocDelimiter;
        $this->client = $client ?? new FileReader();
    }

    /**
     * Gets value or returns default value passed as 2nd argument
     * @param string $name
     * @param        $default
     * @return int|mixed|string|null
     */
    public function get(string $name, $default = null)
    {
        if (!isset($this->cache[$name])) {
            $this->cache[$name] = $this->client->get($this->purifyName($name));
        }

        return $this->cache[$name] ?? $default;
    }

    /**
     * Returns list of keys in the branch
     * @param string $root
     * @return array
     */
    public function getList(string $root = ''): array
    {
        $key = ($root !== '') ? $this->purifyName($root) : $this->ocDelimiter . $this->pathPrefix;

        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $this->client->getList($key . $this->ocDelimiter);
        }

        return $this->cache[$key];
    }

    /**
     * @param string $name
     * @return string
     */
    private function purifyName(string $name): string
    {
        $purifiedName = str_replace($this->separator, $this->ocDelimiter, $name);
        $trimmedName = $this->ocDelimiter . trim($purifiedName, $this->ocDelimiter);

        if (!empty($this->pathPrefix)) {
            return $this->ocDelimiter . $this->pathPrefix . $trimmedName;
        }

        return $trimmedName;
    }
}
