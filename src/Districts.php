<?php
/**
 * District and division lookup. Pure PHP, no WordPress dependency.
 *
 * @package WooBDShipping
 */

declare(strict_types=1);

namespace WooBDShipping;

/**
 * Holds the divisions and districts of Bangladesh and resolves free text to a district code.
 */
final class Districts {

	/**
	 * Common alternative spellings mapped to district codes.
	 *
	 * @var array<string,string>
	 */
	private const ALIASES = array(
		'chittagong'      => 'BD-10',
		'comilla'         => 'BD-08',
		'barisal'         => 'BD-06',
		'jessore'         => 'BD-22',
		'bogra'           => 'BD-03',
		'coxsbazar'       => 'BD-11',
		'coxbazar'        => 'BD-11',
		'nawabganj'       => 'BD-45',
		'chapainawabganj' => 'BD-45',
		'maulvibazar'     => 'BD-38',
		'moulvibazar'     => 'BD-38',
		'jhalakathi'      => 'BD-25',
		'netrokona'       => 'BD-41',
		'brahmanbaria'    => 'BD-04',
		'barguna'         => 'BD-02',
		'khagrachari'     => 'BD-29',
		'lakshmipur'      => 'BD-31',
		'laxmipur'        => 'BD-31',
		'munshigonj'      => 'BD-35',
		'narayangonj'     => 'BD-40',
	);

	/**
	 * District rows keyed by code.
	 *
	 * @var array<string,array{en:string,bn:string,division:string}>
	 */
	private array $districts;

	/**
	 * Division rows keyed by slug.
	 *
	 * @var array<string,array{en:string,bn:string}>
	 */
	private array $divisions;

	/**
	 * Lazily built lookup index of normalised names to codes.
	 *
	 * @var array<string,string>|null
	 */
	private ?array $index = null;

	/**
	 * Constructor.
	 *
	 * @param array<string,array{en:string,bn:string,division:string}>|null $districts Override district data.
	 * @param array<string,array{en:string,bn:string}>|null                 $divisions Override division data.
	 */
	public function __construct( ?array $districts = null, ?array $divisions = null ) {
		$this->districts = $districts ?? require __DIR__ . '/Data/districts.php';
		$this->divisions = $divisions ?? require __DIR__ . '/Data/divisions.php';
	}

	/**
	 * All districts keyed by code.
	 *
	 * @return array<string,array{en:string,bn:string,division:string}>
	 */
	public function all(): array {
		return $this->districts;
	}

	/**
	 * All divisions keyed by slug.
	 *
	 * @return array<string,array{en:string,bn:string}>
	 */
	public function divisions(): array {
		return $this->divisions;
	}

	/**
	 * Whether a district code exists.
	 *
	 * @param string $code District code such as BD-13.
	 */
	public function has( string $code ): bool {
		return isset( $this->districts[ $code ] );
	}

	/**
	 * Get a single district row.
	 *
	 * @param string $code District code.
	 * @return array{en:string,bn:string,division:string}|null
	 */
	public function get( string $code ): ?array {
		return $this->districts[ $code ] ?? null;
	}

	/**
	 * Codes of the districts inside a division.
	 *
	 * @param string $division Division slug.
	 * @return string[]
	 */
	public function inDivision( string $division ): array {
		$codes = array();
		foreach ( $this->districts as $code => $row ) {
			if ( $row['division'] === $division ) {
				$codes[] = $code;
			}
		}
		return $codes;
	}

	/**
	 * Code => label map sorted by English name.
	 *
	 * @param string $lang One of en, bn, both.
	 * @return array<string,string>
	 */
	public function labels( string $lang = 'en' ): array {
		$sorted = $this->districts;
		uasort(
			$sorted,
			static fn( array $a, array $b ): int => strcmp( $a['en'], $b['en'] )
		);

		$out = array();
		foreach ( $sorted as $code => $row ) {
			if ( 'bn' === $lang ) {
				$out[ $code ] = $row['bn'];
			} elseif ( 'both' === $lang ) {
				$out[ $code ] = $row['en'] . ' (' . $row['bn'] . ')';
			} else {
				$out[ $code ] = $row['en'];
			}
		}
		return $out;
	}

	/**
	 * Resolve a code, English name, Bangla name or common alias to a district code.
	 *
	 * @param string $input Raw value, e.g. "BD-13", "dhaka", "ঢাকা", "Chittagong".
	 * @return string|null District code or null when unknown.
	 */
	public function resolve( string $input ): ?string {
		$input = trim( $input );
		if ( '' === $input ) {
			return null;
		}

		$upper = strtoupper( $input );
		if ( isset( $this->districts[ $upper ] ) ) {
			return $upper;
		}

		$key = self::normalise( $input );
		if ( '' === $key ) {
			return null;
		}

		$index = $this->index();
		return $index[ $key ] ?? ( self::ALIASES[ $key ] ?? null );
	}

	/**
	 * Lowercase and strip everything except letters, marks and digits.
	 *
	 * @param string $value Raw text.
	 */
	public static function normalise( string $value ): string {
		$value = mb_strtolower( $value, 'UTF-8' );
		return (string) preg_replace( '/[^\p{L}\p{M}\p{N}]+/u', '', $value );
	}

	/**
	 * Build the name index.
	 *
	 * @return array<string,string>
	 */
	private function index(): array {
		if ( null === $this->index ) {
			$this->index = array();
			foreach ( $this->districts as $code => $row ) {
				$this->index[ self::normalise( $row['en'] ) ] = $code;
				$this->index[ self::normalise( $row['bn'] ) ] = $code;
			}
		}
		return $this->index;
	}
}
