<?php

namespace Starmile\PartnerSdk\Builder;

/**
 * Fluent builder for a product (the contents of a parcel). `name` is required;
 * everything else is optional declaration data used for customs.
 */
final class ProductBuilder
{
    /** @var array<string, mixed> */
    private $attributes = array();

    public function __construct($name)
    {
        $this->attributes['name'] = (string) $name;
    }

    /**
     * @return ProductBuilder
     */
    public static function make($name)
    {
        return new self($name);
    }

    /**
     * @return $this
     */
    public function hsCode($hsCode)
    {
        $this->attributes['hs_code'] = $hsCode;

        return $this;
    }

    /**
     * @param float|int|string $value
     * @param string|null      $currency 3-letter ISO currency code.
     * @return $this
     */
    public function declaredValue($value, $currency = null)
    {
        $this->attributes['declared_value'] = $value;

        if ($currency !== null) {
            $this->attributes['currency'] = $currency;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function currency($currency)
    {
        $this->attributes['currency'] = $currency;

        return $this;
    }

    /**
     * @return $this
     */
    public function weightGrams($grams)
    {
        $this->attributes['weight_grams'] = (int) $grams;

        return $this;
    }

    /**
     * @return $this
     */
    public function quantity($quantity)
    {
        $this->attributes['quantity'] = (int) $quantity;

        return $this;
    }

    /**
     * @return $this
     */
    public function description($description)
    {
        $this->attributes['description'] = $description;

        return $this;
    }

    /**
     * @param string $key
     * @return $this
     */
    public function set($key, $value)
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return $this->attributes;
    }
}
