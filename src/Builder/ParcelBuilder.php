<?php

namespace Starmile\PartnerSdk\Builder;

/**
 * Fluent builder for a parcel (a physical hub package within an order). `item_id`
 * is the partner's per-item reference; `merchant_tracking` is the physical sticker
 * code. Weights and dimensions are records-only — DWS measures the real values at
 * the hub. A parcel must carry at least one product.
 */
final class ParcelBuilder
{
    /** @var array<string, mixed> */
    private $attributes = array();

    /** @var array<int, ProductBuilder|array<string, mixed>> */
    private $products = array();

    /**
     * @param string $itemId The partner's per-item reference.
     */
    public function __construct($itemId)
    {
        $this->attributes['item_id'] = (string) $itemId;
    }

    /**
     * @return ParcelBuilder
     */
    public static function make($itemId)
    {
        return new self($itemId);
    }

    /**
     * The physical sticker / barcode the hub scans to resolve this package.
     *
     * @return $this
     */
    public function merchantTracking($barcode)
    {
        $this->attributes['merchant_tracking'] = $barcode;

        return $this;
    }

    /**
     * @param string $type One of {@see \Starmile\PartnerSdk\Enum\PackageType}.
     * @return $this
     */
    public function packageType($type)
    {
        $this->attributes['package_type'] = $type;

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
    public function dimensionsMm($length, $width, $height)
    {
        $this->attributes['length_mm'] = (int) $length;
        $this->attributes['width_mm'] = (int) $width;
        $this->attributes['height_mm'] = (int) $height;

        return $this;
    }

    /**
     * Add a product. Accepts a {@see ProductBuilder} or a raw array.
     *
     * @param ProductBuilder|array<string, mixed> $product
     * @return $this
     */
    public function addProduct($product)
    {
        $this->products[] = $product;

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
        $products = array();
        foreach ($this->products as $product) {
            $products[] = $product instanceof ProductBuilder ? $product->toArray() : $product;
        }

        $parcel = $this->attributes;
        $parcel['products'] = $products;

        return $parcel;
    }
}
