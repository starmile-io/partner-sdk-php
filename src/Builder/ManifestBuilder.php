<?php

namespace Starmile\PartnerSdk\Builder;

use InvalidArgumentException;

/**
 * Fluent builder for an inbound air manifest.
 *
 * Mirrors the manifest DOCUMENT rather than inventing a shape: the header
 * (waybill, flight, departure, arrival, totals) and one row per package. The
 * totals are computed from the rows you added unless you state your own, so the
 * common case cannot disagree with itself.
 *
 * <code>
 * $manifest = ManifestBuilder::make('784-85403894')
 *     ->flight('CZ5795')
 *     ->origin('CN', 'Corssborder Air Service Limited')
 *     ->etd('2026-10-15 03:10')
 *     ->eta('2026-10-16 20:40')
 *     ->grossWeightKg(193)
 *     ->row('CMX0209075611', 'BOX109022AZ', 1.345)
 *     ->row('CMX0209075612', 'BOX109022AZ', 2.5);
 *
 * $starmile->v2()->manifests()->file($manifest);
 * </code>
 */
final class ManifestBuilder
{
    /** @var array<string, mixed> */
    private $header = array();

    /** @var array<int, array<string, mixed>> */
    private $rows = array();

    /**
     * @param string $awb The master air waybill number, e.g. "784-85403894".
     */
    public function __construct($awb)
    {
        if (trim((string) $awb) === '') {
            throw new InvalidArgumentException('A manifest needs an air waybill number.');
        }

        $this->header['awb'] = (string) $awb;
        // The whole document as it now stands — the default, and what lets a
        // re-filing REMOVE a package that was pulled from the flight.
        $this->header['mode'] = 'full';
    }

    /**
     * @param string $awb
     * @return ManifestBuilder
     */
    public static function make($awb)
    {
        return new self($awb);
    }

    /**
     * The carrier flight or vehicle number.
     *
     * @param string $flight
     * @return $this
     */
    public function flight($flight)
    {
        $this->header['flight'] = (string) $flight;

        return $this;
    }

    /**
     * Where the consignment was built.
     *
     * @param string      $country  ISO 3166-1 alpha-2, e.g. "CN".
     * @param string|null $facility
     * @return $this
     */
    public function origin($country, $facility = null)
    {
        $this->header['origin_country'] = strtoupper((string) $country);

        if ($facility !== null) {
            $this->header['origin_facility'] = (string) $facility;
        }

        return $this;
    }

    /**
     * Estimated time of DEPARTURE — "YYYY-MM-DD HH:MM", destination local time.
     *
     * This is the field the automatic customs filing fires on, and it is the
     * last moment the declaration count can still be corrected. Send it, and
     * keep re-filing the manifest until it passes.
     *
     * @param string $etd
     * @return $this
     */
    public function etd($etd)
    {
        $this->header['etd'] = (string) $etd;

        return $this;
    }

    /**
     * Estimated time of arrival — "YYYY-MM-DD HH:MM", destination local time.
     *
     * @param string $eta
     * @return $this
     */
    public function eta($eta)
    {
        $this->header['eta'] = (string) $eta;

        return $this;
    }

    /**
     * The date the document was produced — "YYYY-MM-DD".
     *
     * @param string $date
     * @return $this
     */
    public function manifestDate($date)
    {
        $this->header['manifest_date'] = (string) $date;

        return $this;
    }

    /**
     * The consignment's GROSS weight in kilograms — bag tare included.
     *
     * Expected to be heavier than the row weights sum to; it is what the waybill
     * is flown and settled on, and the two are reconciled rather than compared
     * for equality.
     *
     * @param float|int|string $kg
     * @return $this
     */
    public function grossWeightKg($kg)
    {
        $this->header['totals_weight_kg'] = $kg;

        return $this;
    }

    /**
     * File this as ONE PART of a consignment split across several documents,
     * instead of the whole thing. Parts accumulate; a `full` filing replaces.
     *
     * Only use this if you genuinely split the document — the default is right
     * for almost every consignment.
     *
     * @return $this
     */
    public function asPart()
    {
        $this->header['mode'] = 'part';

        return $this;
    }

    /**
     * Add a package.
     *
     * @param string                $trackingNumber OUR reference for the package — the
     *                                              tracking number returned when the order
     *                                              was created, not your own id.
     * @param string|null           $bagNo          The physical bag it travels in. Becomes the
     *                                              barcode scanned on arrival, so it must match
     *                                              the label on the bag.
     * @param float|int|string|null $weightKg       Actual weight, up to 3 decimals.
     * @param array<string, mixed>  $details        Optional extras kept for reconciliation and
     *                                              never applied to the order: recipient_name,
     *                                              recipient_phone, district, address, pieces,
     *                                              declared_value, currency, description,
     *                                              hs_codes (array), service_method.
     * @return $this
     */
    public function row($trackingNumber, $bagNo = null, $weightKg = null, array $details = array())
    {
        if (trim((string) $trackingNumber) === '') {
            throw new InvalidArgumentException('A manifest row needs a tracking number.');
        }

        $row = array('tracking_number' => (string) $trackingNumber);

        if ($bagNo !== null) {
            $row['bag_no'] = (string) $bagNo;
        }

        if ($weightKg !== null) {
            $row['weight_kg'] = $weightKg;
        }

        $this->rows[] = array_merge($row, $details);

        return $this;
    }

    /**
     * The request body.
     *
     * Totals are derived from the rows — the package count and the distinct bag
     * count always describe what is actually in the document, so a stated total
     * can never silently contradict it. Only the GROSS weight is yours to state,
     * because it is the one figure the rows cannot produce.
     *
     * @return array<string, mixed>
     */
    public function toArray()
    {
        $header = $this->header;
        $grossWeight = null;

        if (array_key_exists('totals_weight_kg', $header)) {
            $grossWeight = $header['totals_weight_kg'];
            unset($header['totals_weight_kg']);
        }

        $bags = array();

        foreach ($this->rows as $row) {
            if (isset($row['bag_no']) && $row['bag_no'] !== '') {
                $bags[$row['bag_no']] = true;
            }
        }

        $totals = array(
            'shipments' => count($this->rows),
            'bags' => count($bags),
        );

        if ($grossWeight !== null) {
            $totals['weight_kg'] = $grossWeight;
        }

        $header['totals'] = $totals;
        $header['rows'] = $this->rows;

        return $header;
    }
}
