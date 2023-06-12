<?php

declare(strict_types=1);

namespace Vrkansagara\LaraOutPress;

class PhpConfiguration
{
    protected string $key;
    protected string $value;
    protected array|false $iniData;

    /**
     * @param  array|false $iniData
     */
    public function __construct()
    {
        $this->iniData = ini_get_all();
    }


    /**
     * @return string
     */
    public function getIniData()
    {
        return $this->iniData;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param string $key
     */
    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue(string $value): void
    {
        $this->value = $value;
    }




    public function init()
    {
        $iniData = [];
        $iniData['pcre.recursion_limit'] = ini_get('pcre.recursion_limit');
        $iniData['zlib.output_compression'] = ini_get('zlib.output_compression');
        $iniData['zlib.output_compression_level'] = ini_get('zlib.output_compression_level');

        ini_set('pcre.recursion_limit', '16777');
        // Some browser cant get content type.
        ini_set('zlib.output_compression', '4096');
        // Let server decide.
        ini_set('zlib.output_compression_level', '-1');

        return $iniData;
    }
}
