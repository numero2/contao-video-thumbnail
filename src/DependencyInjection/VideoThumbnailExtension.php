<?php

/**
 * Video Thumbnail Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\VideoThumbnailBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;


class VideoThumbnailExtension extends Extension {


    /**
     * {@inheritdoc}
     *
     * Must match the root node of Configuration — the derived alias would
     * be "video_thumbnail" and any documented "video_thumbnail"
     * app config would fail the container compilation.
     */
    public function getAlias(): string {
        return 'video_thumbnail';
    }


    /**
     * {@inheritdoc}
     */
    public function load( array $mergedConfig, ContainerBuilder $container ): void {

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../../config')
        );

        $loader->load('services.yaml');

        $config = $this->processConfiguration(new Configuration(), $mergedConfig);

        $container->setParameter(
            'video_thumbnail.video_extensions',
            $config['video_extensions']
        );

        $container->setParameter(
            'video_thumbnail.poster_suffix',
            $config['poster_suffix']
        );
    }
}
