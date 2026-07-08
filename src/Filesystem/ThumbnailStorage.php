<?php

/**
 * Video Thumbnail Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\VideoThumbnailBundle\Filesystem;

use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\FilesModel;
use Symfony\Component\Filesystem\Path;


class ThumbnailStorage {


    public function __construct(
        private readonly VirtualFilesystemInterface $filesStorage,
        private readonly string $uploadPath,
        private readonly string $posterSuffix = '-poster.jpg',
    ) {
    }


    /**
     * Captures a JPEG and stores it as a poster file next to the video.
     *
     * @param string $videoPath  Full DB path of the video (e.g. "files/videos/clip.mp4").
     * @param string $jpegBinary Raw JPEG binary.
     * @return string            Binary UUID (binary(16)) of the created/overwritten poster file.
     */
    public function store( string $videoPath, string $jpegBinary ): string {

        $relVideoPath = $this->stripUploadPrefix($videoPath);

        $dir  = Path::getDirectory($relVideoPath);
        $base = Path::getFilenameWithoutExtension($relVideoPath);

        $posterRel = ($dir !== '' ? $dir . '/' : '') . $base . $this->posterSuffix;

        // Write via VirtualFilesystem — syncs DBAFS automatically.
        $this->filesStorage->write($posterRel, $jpegBinary);

        $model = FilesModel::findByPath($this->uploadPath . '/' . $posterRel);

        if( $model === null ) {
            throw new \RuntimeException(sprintf(
                'DBAFS record not found for "%s" after write.',
                $posterRel
            ));
        }

        return $model->uuid; // binary(16)
    }


    /**
     * Deletes the poster file identified by its binary UUID.
     * Does nothing if the UUID is empty or the file no longer exists.
     */
    public function deleteByUuid( mixed $uuid ): void {

        if( empty($uuid) ) {
            return;
        }

        $model = FilesModel::findByPk($uuid);

        if( $model === null ) {
            return;
        }

        $relPath = $this->stripUploadPrefix($model->path);

        if( $this->filesStorage->fileExists($relPath) ) {
            $this->filesStorage->delete($relPath);
        }
    }


    private function stripUploadPrefix( string $fullPath ): string {

        $prefix = rtrim($this->uploadPath, '/') . '/';

        if( str_starts_with($fullPath, $prefix) ) {
            return substr($fullPath, \strlen($prefix));
        }

        return ltrim($fullPath, '/');
    }
}
