<?php
/**
 * Delivery time estimates per zone and district. Pure PHP.
 *
 * @package WooBDShipping
 */

declare(strict_types=1);

namespace WooBDShipping;

use DateTimeImmutable;

/**
 * Turns "1-2" style day ranges, an order cut-off time and weekly off days into a
 * customer-facing estimate such as "1-2 days" or "14 Nov - 16 Nov".
 */
final class DeliveryEstimate {

	public const MAX_DAYS = 60;

	public const FORMAT_DAYS  = 'days';
	public const FORMAT_DATES = 'dates';

	public const PLACEHOLDER = '{estimate}';

	/**
	 * Weekly off day presets. Values are ISO-8601 weekday numbers (1 = Monday ... 7 = Sunday).
	 */
	public const OFF_DAY_PRESETS = array(
		'none'    => array(),
		'fri'     => array( 5 ),
		'fri_sat' => array( 5, 6 ),
	);

	/**
	 * Constructor.
	 *
	 * @param array<string,array{0:int,1:int}> $zoneDays      Day range per zone (RateCalculator::ZONE_* keys).
	 * @param array<string,array{0:int,1:int}> $overrides     Day range per district code.
	 * @param int|null                         $cutoffMinutes Minutes after midnight; later orders ship the next working day. Null disables it.
	 * @param int[]                            $offDays       ISO weekday numbers on which nothing is dispatched or delivered.
	 */
	public function __construct(
		public readonly array $zoneDays = array(),
		public readonly array $overrides = array(),
		public readonly ?int $cutoffMinutes = null,
		public readonly array $offDays = array()
	) {
	}

	/**
	 * Build an estimator from the stored plugin settings.
	 *
	 * @param array<string,mixed> $settings  Plugin settings.
	 * @param Districts           $districts District lookup used to resolve override keys.
	 */
	public static function fromSettings( array $settings, Districts $districts ): self {
		$zones = array();
		$keys  = array(
			RateCalculator::ZONE_INSIDE_DHAKA => 'delivery_inside_dhaka',
			RateCalculator::ZONE_SUB_AREA     => 'delivery_sub_area',
			RateCalculator::ZONE_OUTSIDE      => 'delivery_outside',
		);
		foreach ( $keys as $zone => $key ) {
			$range = self::parseRange( self::text( $settings[ $key ] ?? '' ) );
			if ( null !== $range ) {
				$zones[ $zone ] = $range;
			}
		}

		$preset = self::text( $settings['delivery_off_days'] ?? 'none' );

		return new self(
			$zones,
			self::parseOverrides( self::text( $settings['delivery_overrides'] ?? '' ), $districts ),
			self::parseCutoff( self::text( $settings['delivery_cutoff'] ?? '' ) ),
			self::OFF_DAY_PRESETS[ $preset ] ?? array()
		);
	}

	/**
	 * Parse a day range: "3", "1-2", "2 to 4", "১-২" or "1–2 days". Reversed ranges are swapped.
	 *
	 * @param string $text Raw text.
	 * @return array{0:int,1:int}|null Null when the text is not a range or exceeds MAX_DAYS.
	 */
	public static function parseRange( string $text ): ?array {
		$text = strtolower( trim( PhoneNormalizer::toAsciiDigits( $text ) ) );
		if ( 1 !== preg_match( '/^(\d{1,3})(?:\s*(?:-|–|—|to)\s*(\d{1,3}))?(?:\s*days?)?$/u', $text, $m ) ) {
			return null;
		}
		$min = (int) $m[1];
		$max = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : $min;
		if ( $min > $max ) {
			list( $min, $max ) = array( $max, $min );
		}
		return $max > self::MAX_DAYS ? null : array( $min, $max );
	}

	/**
	 * Parse override lines such as "Sylhet=2-3", "BD-60: 4" or "ঢাকা,১". Lines starting with # are ignored.
	 *
	 * @param string    $text      Multi-line text.
	 * @param Districts $districts District lookup.
	 * @return array<string,array{0:int,1:int}>
	 */
	public static function parseOverrides( string $text, Districts $districts ): array {
		$out   = array();
		$lines = preg_split( '/\R/u', $text ) ?: array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$parts = preg_split( '/\s*[=:,]\s*/u', $line, 2 );
			if ( ! is_array( $parts ) || 2 !== count( $parts ) ) {
				continue;
			}
			$code  = $districts->resolve( $parts[0] );
			$range = self::parseRange( $parts[1] );
			if ( null === $code || null === $range ) {
				continue;
			}
			$out[ $code ] = $range;
		}

		return $out;
	}

	/**
	 * Parse a 24-hour cut-off time: "14", "14:30", "9.00" or "১৬:০০".
	 *
	 * @param string $text Raw text.
	 * @return int|null Minutes after midnight, or null when empty or invalid.
	 */
	public static function parseCutoff( string $text ): ?int {
		$text = trim( PhoneNormalizer::toAsciiDigits( $text ) );
		if ( 1 !== preg_match( '/^(\d{1,2})(?:[:.](\d{2}))?$/', $text, $m ) ) {
			return null;
		}
		$hour   = (int) $m[1];
		$minute = isset( $m[2] ) ? (int) $m[2] : 0;
		if ( $hour > 23 || $minute > 59 ) {
			return null;
		}
		return $hour * 60 + $minute;
	}

	/**
	 * Day range for a district: override first, then the zone range.
	 *
	 * @param string $districtCode District code.
	 * @param string $zone         Zone the district belongs to (RateCalculator::zoneFor()).
	 * @return array{0:int,1:int}|null Null when nothing is configured.
	 */
	public function rangeFor( string $districtCode, string $zone ): ?array {
		return $this->overrides[ $districtCode ] ?? $this->zoneDays[ $zone ] ?? null;
	}

	/**
	 * Whether a date falls on a weekly off day.
	 *
	 * @param DateTimeImmutable $date Date to check.
	 */
	public function isOffDay( DateTimeImmutable $date ): bool {
		return in_array( (int) $date->format( 'N' ), $this->offDays, true );
	}

	/**
	 * Day the parcel leaves: today, or the next working day after the cut-off or on an off day.
	 *
	 * @param DateTimeImmutable $now Order time in the store timezone.
	 */
	public function dispatchDate( DateTimeImmutable $now ): DateTimeImmutable {
		$day = $now->setTime( 0, 0 );
		if ( null !== $this->cutoffMinutes && ! $this->isOffDay( $day ) ) {
			$minutes = (int) $now->format( 'G' ) * 60 + (int) $now->format( 'i' );
			if ( $minutes >= $this->cutoffMinutes ) {
				$day = $day->modify( '+1 day' );
			}
		}
		return $this->skipOffDays( $day );
	}

	/**
	 * Add working days to a date, skipping the weekly off days.
	 *
	 * @param DateTimeImmutable $from Start date (a working day).
	 * @param int               $days Working days to add.
	 */
	public function addWorkingDays( DateTimeImmutable $from, int $days ): DateTimeImmutable {
		$date = $from;
		for ( $i = 0; $i < $days; $i++ ) {
			$date = $this->skipOffDays( $date->modify( '+1 day' ) );
		}
		return $date;
	}

	/**
	 * Earliest and latest arrival dates for an order placed at $now.
	 *
	 * @param array{0:int,1:int} $range Day range from rangeFor().
	 * @param DateTimeImmutable  $now   Order time in the store timezone.
	 * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
	 */
	public function dateRange( array $range, DateTimeImmutable $now ): array {
		$dispatch = $this->dispatchDate( $now );
		$earliest = $this->addWorkingDays( $dispatch, $range[0] );
		return array( $earliest, $this->addWorkingDays( $earliest, $range[1] - $range[0] ) );
	}

	/**
	 * Text for a day range: "Same day", "1 day", "3 days" or "1-2 days".
	 *
	 * @param array{0:int,1:int}   $range Day range.
	 * @param array<string,string> $words Optional translated templates: same_day, day and days (with %s for the number or range).
	 */
	public static function daysText( array $range, array $words = array() ): string {
		$words += array(
			'same_day' => 'Same day',
			'day'      => '%s day',
			'days'     => '%s days',
		);
		list( $min, $max ) = $range;
		if ( 0 === $max ) {
			return $words['same_day'];
		}
		if ( $min === $max ) {
			return sprintf( 1 === $max ? $words['day'] : $words['days'], (string) $max );
		}
		return sprintf( $words['days'], $min . '-' . $max );
	}

	/**
	 * Text for an arrival window: "14 Nov" or "14 Nov - 16 Nov".
	 *
	 * @param array{0:DateTimeImmutable,1:DateTimeImmutable} $dates     Result of dateRange().
	 * @param callable|null                                  $formatter Receives a DateTimeImmutable and returns the label; defaults to "j M".
	 */
	public static function datesText( array $dates, ?callable $formatter = null ): string {
		$formatter ??= static fn ( DateTimeImmutable $date ): string => $date->format( 'j M' );
		$from        = (string) $formatter( $dates[0] );
		$to          = (string) $formatter( $dates[1] );
		return $from === $to ? $from : $from . ' - ' . $to;
	}

	/**
	 * Full estimate text for a district, or an empty string when no range is configured.
	 *
	 * @param string               $districtCode District code.
	 * @param string               $zone         Zone the district belongs to.
	 * @param DateTimeImmutable    $now          Order time in the store timezone.
	 * @param string               $format       FORMAT_DAYS or FORMAT_DATES.
	 * @param array<string,string> $words        Translated templates for daysText().
	 * @param callable|null        $formatter    Date formatter for datesText().
	 */
	public function estimate( string $districtCode, string $zone, DateTimeImmutable $now, string $format = self::FORMAT_DAYS, array $words = array(), ?callable $formatter = null ): string {
		$range = $this->rangeFor( $districtCode, $zone );
		if ( null === $range ) {
			return '';
		}
		if ( self::FORMAT_DATES === $format ) {
			return self::datesText( $this->dateRange( $range, $now ), $formatter );
		}
		return self::daysText( $range, $words );
	}

	/**
	 * Put an estimate into a label template. A template without {estimate} gets the estimate appended in brackets.
	 *
	 * @param string $template Template such as "Delivery in {estimate}"; may be empty.
	 * @param string $estimate Result of estimate().
	 * @param string $label    Shipping method title the template is combined with.
	 */
	public static function decorate( string $template, string $estimate, string $label ): string {
		if ( '' === $estimate ) {
			return $label;
		}
		$template = trim( $template );
		$text     = str_contains( $template, self::PLACEHOLDER ) ? str_replace( self::PLACEHOLDER, $estimate, $template ) : $estimate;
		return '' === trim( $label ) ? $text : $label . ' (' . $text . ')';
	}

	/**
	 * Move forward to the first date that is not an off day.
	 *
	 * @param DateTimeImmutable $date Start date.
	 */
	private function skipOffDays( DateTimeImmutable $date ): DateTimeImmutable {
		// A week where every day is off would never end; treat it as "no off days".
		if ( count( array_unique( $this->offDays ) ) >= 7 ) {
			return $date;
		}
		while ( $this->isOffDay( $date ) ) {
			$date = $date->modify( '+1 day' );
		}
		return $date;
	}

	/**
	 * Coerce a stored setting into a string.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function text( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
