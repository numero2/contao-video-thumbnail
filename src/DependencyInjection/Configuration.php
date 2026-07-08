<?php

/**
 * Video Thumbnail Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\VideoThumbnailBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;


class Configuration implements ConfigurationInterface {


    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder {

        $treeBuilder = new TreeBuilder('video_thumbnail');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('video_extensions')
                    ->info('File extensions treated as video files.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['mp4', 'm4v', 'mov', 'webm', 'ogv', 'ogg'])
                ->end()
                ->scalarNode('poster_suffix')
                    ->info('Suffix appended to the video basename when creating the poster file.')
                    ->defaultValue('-poster.jpg')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
