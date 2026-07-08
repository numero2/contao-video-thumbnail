<?php

/**
 * Video Thumbnail Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\VideoThumbnailBundle\Widget;

use Contao\Environment;
use Contao\FilesModel;
use Contao\Folder;
use Contao\Input;
use Contao\System;
use Contao\Widget;


class VideoThumbnailWidget extends Widget {


    /**
     * Template — without it Widget::parse() returns an empty string and
     * the whole field (including label and help text) renders invisible.
     * @var string
     */
    protected $strTemplate = 'be_widget';

    /**
     * Submit user input — without this the DataContainer skips validate()
     * and save() entirely (DataContainer::row() checks submitInput()),
     * so the save_callback would never be triggered.
     * @var bool
     */
    protected $blnSubmitInput = true;

    /** Step in seconds per frame-step button click (≈ 1 frame @ 25 fps). */
    private const FRAME_STEP = 0.04;

    /** JPEG quality passed to canvas.toDataURL. */
    private const JPEG_QUALITY = 0.85;

    /** Sentinel value posted by the JS clear-button. */
    public const DELETE_TOKEN = '__delete__';


    /**
     * {@inheritdoc}
     */
    public function validate(): void {

        $raw = Input::postRaw($this->strName);

        // Empty → field was not touched; keep existing value as-is.
        // The save_callback (C2) will detect the empty string and preserve
        // the current UUID without overwriting.
        $this->varValue = ($raw !== null) ? $raw : '';
    }


    /**
     * {@inheritdoc}
     */
    public function generate(): string {

        // Fetch via plain __get once — isset()/?? on the magic property chain
        // always yield null/false because DataContainer defines no __isset.
        $record = $this->activeRecord;

        // Resolve video file path from the active record or GET parameter.
        $videoPath = $record?->path ?? Input::get('id');

        // Videos in protected folders cannot be played in the browser — the
        // template renders a warning instead of the player then.
        $isPublic = true;

        try {
            $isPublic = (new Folder(\dirname((string) $videoPath)))->isUnprotected();
        } catch( \Exception ) {
            // Path not resolvable as folder — leave the player visible.
        }

        // Resolve existing thumbnail (UUID stored as binary(16)).
        $thumbnailUrl = null;

        if( !empty($this->varValue) ) {
            $posterModel = FilesModel::findByPk($this->varValue);
            if( $posterModel !== null ) {
                $thumbnailUrl = Environment::get('base') . $posterModel->path;
            }
        }

        // Playback position the current thumbnail was captured at (seconds).
        $thumbnailTime = null;

        if( $thumbnailUrl !== null && is_numeric($record?->videoThumbnailTime) ) {
            $thumbnailTime = (float) $record->videoThumbnailTime;
        }

        return System::getContainer()->get('twig')->render(
            '@Contao_VideoThumbnailBundle/backend/video_thumbnail_widget.html.twig',
            [
                'name'           => $this->strName,
                'id'             => $this->strId,
                'is_public'      => $isPublic,
                'video_url'      => Environment::get('base') . ltrim((string) $videoPath, '/'),
                'thumbnail_url'  => $thumbnailUrl,
                'thumbnail_time' => $thumbnailTime,
                'frame_step'     => self::FRAME_STEP,
                'jpeg_quality'   => self::JPEG_QUALITY,
            ]
        );
    }
}
