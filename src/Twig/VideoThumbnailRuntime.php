<?php

/**
 * Video Thumbnail Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\VideoThumbnailBundle\Twig;

use Contao\CoreBundle\Filesystem\FilesystemItem;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Doctrine\DBAL\Connection;
use Twig\Extension\RuntimeExtensionInterface;


class VideoThumbnailRuntime implements RuntimeExtensionInterface {


    public function __construct(
        private readonly Connection $connection,
        private readonly VirtualFilesystemInterface $filesStorage,
        private readonly string $uploadPath,
    ) {
    }


    /**
     * Returns the public URL of the video thumbnail poster for the first video
     * source that has one, or null if none is found.
     *
     * @param list<FilesystemItem> $sourceFiles
     */
    public function getPosterUrl( array $sourceFiles ): ?string {

        foreach( $sourceFiles as $item ) {

            if( !$item instanceof FilesystemItem || !$item->isVideo() ) {
                continue;
            }

            $fullPath = rtrim($this->uploadPath, '/') . '/' . $item->getPath();

            $row = $this->connection->fetchAssociative(
                'SELECT videoThumbnail FROM tl_files WHERE path = ? AND videoThumbnail IS NOT NULL',
                [$fullPath]
            );

            if( $row === false ) {
                continue;
            }

            $posterRow = $this->connection->fetchOne(
                'SELECT path FROM tl_files WHERE uuid = ?',
                [$row['videoThumbnail']]
            );

            if( $posterRow === false ) {
                continue;
            }

            $prefix       = rtrim($this->uploadPath, '/') . '/';
            $posterRelPath = str_starts_with($posterRow, $prefix)
                ? substr($posterRow, \strlen($prefix))
                : $posterRow;

            $uri = $this->filesStorage->generatePublicUri($posterRelPath);

            return $uri !== null ? (string) $uri : null;
        }

        return null;
    }
}
