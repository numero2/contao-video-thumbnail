<?php

/**
 * Video Thumbnail Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


$GLOBALS['TL_DCA']['tl_files']['fields']['videoThumbnail'] = [
    'exclude' => true,
    'inputType' => 'videoThumbnail',
    'eval' => [
        'versionize' => false,
    ],
    'sql' => "binary(16) NULL",
];

// Playback position (in seconds) the thumbnail was captured at. Not editable
// on its own — written by the videoThumbnail save_callback and used by the
// widget to seek the player to the stored position when editing.
$GLOBALS['TL_DCA']['tl_files']['fields']['videoThumbnailTime'] = [
    'sql' => ['type' => 'float', 'notnull' => false],
];
