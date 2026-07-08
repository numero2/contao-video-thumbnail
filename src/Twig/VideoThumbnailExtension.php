<?php

/**
 * Video Thumbnail Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\VideoThumbnailBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;


class VideoThumbnailExtension extends AbstractExtension {


    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array {

        return [
            new TwigFunction(
                'video_thumbnail_poster',
                [VideoThumbnailRuntime::class, 'getPosterUrl']
            ),
        ];
    }
}
