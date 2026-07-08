<?php

/**
 * Video Thumbnail Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\VideoThumbnailBundle\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Input;
use Doctrine\DBAL\Connection;
use numero2\VideoThumbnailBundle\Filesystem\ThumbnailStorage;
use numero2\VideoThumbnailBundle\Widget\VideoThumbnailWidget;
use Symfony\Component\Filesystem\Path;


class FilesListener {


    public function __construct(
        private readonly Connection $connection,
        private readonly ThumbnailStorage $thumbnailStorage,
        private readonly array $videoExtensions = ['mp4', 'm4v', 'mov', 'webm', 'ogv', 'ogg'],
    ) {
    }


    /**
     * Adds the video_thumbnail_legend palette for video files (B2).
     *
     * Registered as onpalette (not onload) because DC_Folder rebuilds the
     * palette per record via getPalette() — in editAll for every selected
     * file, where an onload callback has no usable id.
     */
    #[AsCallback(table: 'tl_files', target: 'config.onpalette')]
    public function adjustPalettes( string $palette, DataContainer $dc ): string {

        if( !$dc->id ) {
            return $palette;
        }

        $ext = Path::getExtension((string) $dc->id, true);

        if( !in_array($ext, $this->videoExtensions, true) ) {
            return $palette;
        }

        return PaletteManipulator::create()
            ->addLegend('video_thumbnail_legend', 'name_legend', PaletteManipulator::POSITION_AFTER)
            ->addField('videoThumbnail', 'video_thumbnail_legend', PaletteManipulator::POSITION_APPEND)
            ->applyToString($palette);
    }


    /**
     * Registers the widget's backend assets (B3).
     */
    #[AsCallback(table: 'tl_files', target: 'config.onload')]
    public function registerAssets( DataContainer $dc ): void {

        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/videothumbnail/backend/video-thumbnail.js|static';
        $GLOBALS['TL_CSS'][]        = 'bundles/videothumbnail/backend/backend.css|static';
    }


    /**
     * Converts the widget's posted value into a binary UUID for storage (C2).
     *
     * Posted value variants:
     *   ''              → no change; keep the existing UUID unchanged.
     *   '__delete__'    → remove the poster file; store NULL.
     *   'data:image/…'  → new JPEG capture; decode, persist, return UUID.
     */
    #[AsCallback(table: 'tl_files', target: 'fields.videoThumbnail.save')]
    public function saveThumbnail( mixed $value, DataContainer $dc ): mixed {

        $value = (string) $value;

        // Plain __get access — never use isset() on the activeRecord chain,
        // it breaks on DataContainer's missing __isset (see widget).
        $currentUuid = $dc->activeRecord?->videoThumbnail;

        // No change — preserve existing UUID.
        if( $value === '' ) {
            return $currentUuid;
        }

        // Delete token — remove poster file and clear the column.
        if( $value === VideoThumbnailWidget::DELETE_TOKEN ) {
            $this->thumbnailStorage->deleteByUuid($currentUuid);
            $this->updateTimestamp($dc, null);
            return null;
        }

        // base64 data-URL — decode binary, overwrite poster file, return UUID.
        if( str_starts_with($value, 'data:image/jpeg;base64,') ) {
            $jpegBinary = base64_decode(substr($value, 23), true);

            if( $jpegBinary === false || $jpegBinary === '' ) {
                return $currentUuid;
            }

            // The widget posts the capture position in a companion input so
            // the player can seek back to it when the file is edited again.
            $time = Input::post($dc->inputName . '_time');
            $this->updateTimestamp($dc, is_numeric($time) ? max(0.0, (float) $time) : null);

            return $this->thumbnailStorage->store($dc->id, $jpegBinary);
        }

        return null;
    }


    /**
     * Persists the capture position alongside the thumbnail UUID. Written
     * directly since videoThumbnailTime has no widget of its own.
     */
    private function updateTimestamp( DataContainer $dc, ?float $time ): void {

        $this->connection->update(
            'tl_files',
            ['videoThumbnailTime' => $time],
            ['path' => $dc->id]
        );
    }
}
