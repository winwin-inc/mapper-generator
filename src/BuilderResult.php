<?php

declare(strict_types=1);

namespace winwin\mapper;

class BuilderResult
{
    /**
     * @var string
     */
    private $targetFile;

    /**
     * @var bool
     */
    private $targetChanged = true;

    /**
     * @var string
     */
    private $targetCode = '';

    /**
     * @var string
     */
    private $builderFile = '';

    /**
     * @var bool
     */
    private $builderChanged = true;

    /**
     * @var string
     */
    private $builderCode = '';

    /**
     * BuilderResult constructor.
     *
     * @param string $targetFile
     */
    public function __construct(string $targetFile)
    {
        $this->targetFile = $targetFile;
    }

    /**
     * @return string
     */
    public function getTargetFile(): string
    {
        return $this->targetFile;
    }

    /**
     * @return bool
     */
    public function isTargetChanged(): bool
    {
        return $this->targetChanged;
    }

    /**
     * @param bool $targetChanged
     *
     * @return BuilderResult
     */
    public function setTargetChanged(bool $targetChanged): BuilderResult
    {
        $this->targetChanged = $targetChanged;

        return $this;
    }

    /**
     * @return string
     */
    public function getTargetCode(): string
    {
        return $this->targetCode;
    }

    /**
     * @param string $targetCode
     *
     * @return BuilderResult
     */
    public function setTargetCode(string $targetCode): BuilderResult
    {
        $this->targetCode = $targetCode;

        return $this;
    }

    /**
     * @return string
     */
    public function getBuilderFile(): string
    {
        return $this->builderFile;
    }

    /**
     * @param string $builderFile
     *
     * @return BuilderResult
     */
    public function setBuilderFile(string $builderFile): BuilderResult
    {
        $this->builderFile = $builderFile;

        return $this;
    }

    /**
     * @return bool
     */
    public function isBuilderChanged(): bool
    {
        return $this->builderChanged;
    }

    /**
     * @param bool $builderChanged
     *
     * @return BuilderResult
     */
    public function setBuilderChanged(bool $builderChanged): BuilderResult
    {
        $this->builderChanged = $builderChanged;

        return $this;
    }

    /**
     * @return string
     */
    public function getBuilderCode(): string
    {
        return $this->builderCode;
    }

    /**
     * @param string $builderCode
     *
     * @return BuilderResult
     */
    public function setBuilderCode(string $builderCode): BuilderResult
    {
        $this->builderCode = $builderCode;

        return $this;
    }

    public function replace(): void
    {
        if ($this->targetChanged) {
            file_put_contents($this->targetFile, $this->targetCode);
        }
        if ($this->builderChanged) {
            file_put_contents($this->builderFile, $this->builderCode);
        }
    }
}
