<?php
/**
 * Minimal Loupe library stubs for unit tests.
 *
 * These stand in for the real loupe/loupe classes so that
 * WP_Loupe_Admin_Indexer can be loaded and mocked without
 * having the Loupe library installed in this plugin.
 */

namespace Loupe\Loupe\Config {
	class TypoTolerance {
		/** @return self */
		public static function create(): self {
			return new self();
		}

		/** @return self */
		public static function disabled(): self {
			return new self();
		}

		/** @return self */
		public function withAlphabetSize( $s ): self {
			return $this;
		}

		/** @return self */
		public function withIndexLength( $l ): self {
			return $this;
		}

		/** @return self */
		public function withFirstCharTypoCountsDouble( bool $v ): self {
			return $this;
		}

		/** @return self */
		public function withEnabledForPrefixSearch( bool $v ): self {
			return $this;
		}

		/** @return self */
		public function withTypoThresholds( array $t ): self {
			return $this;
		}
	}
}

namespace Loupe\Loupe {

	use Loupe\Loupe\Config\TypoTolerance;

	class Configuration {
		/** @return self */
		public static function create(): self {
			return new self();
		}

		/** @return self */
		public function withPrimaryKey( string $k ): self {
			return $this;
		}

		/** @return self */
		public function withSearchableAttributes( array $a ): self {
			return $this;
		}

		/** @return self */
		public function withFilterableAttributes( array $a ): self {
			return $this;
		}

		/** @return self */
		public function withSortableAttributes( array $a ): self {
			return $this;
		}

		/** @return self */
		public function withMaxQueryTokens( int $n ): self {
			return $this;
		}

		/** @return self */
		public function withMinTokenLengthForPrefixSearch( int $n ): self {
			return $this;
		}

		/** @return self */
		public function withLanguages( array $l ): self {
			return $this;
		}

		/** @return self */
		public function withTypoTolerance( TypoTolerance $t ): self {
			return $this;
		}
	}

	class LoupeFactory {
		public function create( string $path, Configuration $config ): Loupe {
			return new Loupe();
		}
	}

	class Loupe {
		/** @return SearchResult */
		public function search( SearchParameters $p ): SearchResult {
			return new SearchResult();
		}

		public function addDocument( array $d ): void {}

		public function addDocuments( array $d ): void {}

		public function deleteDocument( int $id ): void {}

		public function deleteAllDocuments(): void {}
	}

	class SearchParameters {
		/** @return self */
		public static function create(): self {
			return new self();
		}

		/** @return self */
		public function withQuery( string $q ): self {
			return $this;
		}

		/** @return self */
		public function withAttributesToRetrieve( array $a ): self {
			return $this;
		}

		/** @return self */
		public function withShowRankingScore( bool $b ): self {
			return $this;
		}

		/** @return self */
		public function withLimit( int $l ): self {
			return $this;
		}
	}

	class SearchResult {
		/** @return array<string,mixed> */
		public function toArray(): array {
			return [ 'hits' => [] ];
		}
	}
}
