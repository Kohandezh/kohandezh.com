<?php
/**
 * Bounded PDF viewer facts and stream plan over the canonical release PDF.
 *
 * Fail-closed: the release manifest and the PDF file must both validate
 * before any fact is served. The stream is executed by the route layer;
 * this class only produces the plan.
 *
 * @package kohandezh-knowledge
 */

if ( ! defined( 'KBK_TESTING' ) && ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KBK_AI_Book_Pdf
 */
class KBK_AI_Book_Pdf {

	const STATUS_READY            = 'READY';
	const STATUS_CONFIG_REQUIRED  = 'CONFIG_REQUIRED';
	const STATUS_ARTIFACT_INVALID = 'ARTIFACT_INVALID';

	const MANIFEST_RELPATH = 'provenance/release-manifest.json';
	const PDF_RELPATH      = 'release/06_book_fa.pdf';
	const FILENAME         = 'kohandezh-ai-book-fa.pdf';
	const STREAM_PATH      = '/ai-book/pdf/file/';

	/** Editorial constant documented in CONTENT_SOURCE_MAP (the manifest carries no page count). */
	const CANONICAL_PAGES = 1292;

	/** @var KBK_AI_Book_Repository */
	private $repository;

	/** @var string|null */
	private $root_override;

	/** @var array<string,mixed>|null */
	private $meta;

	/** @var string|null */
	private $error;

	/**
	 * @param KBK_AI_Book_Repository $repository Validated repository.
	 * @param string|null            $root_override Test-only root override.
	 */
	public function __construct( KBK_AI_Book_Repository $repository, ?string $root_override = null ) {
		$this->repository    = $repository;
		$this->root_override = $root_override;
	}

	public function status(): string {
		$this->load();
		return null !== $this->error ? $this->error : self::STATUS_READY;
	}

	/**
	 * Bounded edition/file facts for the viewer panel.
	 *
	 * @return array<string,mixed>|null
	 */
	public function meta(): ?array {
		$this->load();
		return null === $this->error ? $this->meta : null;
	}

	/**
	 * The executable stream plan. Non-READY states carry no path.
	 *
	 * @return array{status:string,path:string,filename:string,bytes:int,headers:array<int,string>}
	 */
	public function stream_plan(): array {
		$this->load();
		if ( null !== $this->error || null === $this->meta ) {
			return array(
				'status'   => null !== $this->error ? $this->error : self::STATUS_ARTIFACT_INVALID,
				'path'     => '',
				'filename' => '',
				'bytes'    => 0,
				'headers'  => array(),
			);
		}
		$filename = 'kohandezh-ai-book-' . $this->meta['edition'] . '-fa.pdf';
		$etag     = '"' . $this->meta['sha256'] . '"';
		return array(
			'status'   => self::STATUS_READY,
			'path'     => $this->meta['path'],
			'filename' => $filename,
			'bytes'    => $this->meta['bytes'],
			'etag'     => $etag,
			'headers'  => array(
				'Content-Type: application/pdf',
				'Content-Length: ' . (string) $this->meta['bytes'],
				'Content-Disposition: inline; filename="' . $filename . '"',
				'X-Robots-Tag: noindex, nofollow',
				'Accept-Ranges: none',
				'ETag: ' . $etag,
				'Cache-Control: private, max-age=0, must-revalidate',
			),
		);
	}

	/**
	 * Pure conditional-request decision for the stream executor: the strong
	 * ETag is the artifact's manifest sha256, so a matching If-None-Match
	 * yields a bodyless 304 without re-reading the 38MB file.
	 */
	public static function etag_matches( ?string $if_none_match, string $etag ): bool {
		if ( null === $if_none_match || '' === $if_none_match ) {
			return false;
		}
		$candidates = array_map( 'trim', explode( ',', $if_none_match ) );
		if ( in_array( '*', $candidates, true ) ) {
			return true;
		}
		return in_array( $etag, $candidates, true ) || in_array( 'W/' . $etag, $candidates, true );
	}

	private function load(): void {
		if ( null !== $this->meta || null !== $this->error ) {
			return;
		}
		$root = null !== $this->root_override && '' !== $this->root_override
			? rtrim( $this->root_override, '/' )
			: rtrim( $this->repository->root(), '/' );
		$manifest_path = $root . '/' . self::MANIFEST_RELPATH;
		$pdf_path      = $root . '/' . self::PDF_RELPATH;
		if ( ! is_file( $manifest_path ) || ! is_readable( $manifest_path ) ) {
			$this->error = self::STATUS_CONFIG_REQUIRED;
			return;
		}
		try {
			$raw      = (string) file_get_contents( $manifest_path );
			$manifest = json_decode( $raw, true );
			$facts    = KBK_AI_Book_Artifacts::validate_release_manifest( $manifest, $this->repository->summary()['edition_id'] );
			$bytes    = KBK_AI_Book_Artifacts::validate_pdf_file( $pdf_path, $facts['bytes'] );
		} catch ( InvalidArgumentException | UnexpectedValueException $error ) {
			$this->error = self::STATUS_ARTIFACT_INVALID;
			return;
		}
		if ( ! is_file( $pdf_path ) || ! is_readable( $pdf_path ) ) {
			$this->error = self::STATUS_ARTIFACT_INVALID;
			return;
		}
		$this->meta = array(
			'status'      => self::STATUS_READY,
			'edition'     => $facts['edition_id'],
			'build_date'  => $facts['build_date'],
			'publisher'   => $facts['publisher'],
			'merkle_root' => $facts['merkle_root'],
			'sha256'      => $facts['sha256'],
			'bytes'       => $bytes,
			'pages'       => self::CANONICAL_PAGES,
			'path'        => $pdf_path,
			'stream_url'  => self::STREAM_PATH,
		);
	}
}
