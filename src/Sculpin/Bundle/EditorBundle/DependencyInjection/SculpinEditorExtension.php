<?php

declare(strict_types=1);

/*
 * This file is a part of Sculpin.
 *
 * (c) Dragonfly Development Inc.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sculpin\Bundle\EditorBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Sculpin\Contrib\ProxySourceCollection\ProxySourceItem;
use Sculpin\Contrib\ProxySourceCollection\Sorter\DefaultSorter;
use Sculpin\Contrib\ProxySourceCollection\ProxySourceCollection;
use Sculpin\Core\Source\Filter\AntPathFilter;
use Sculpin\Core\Source\Filter\MetaFilter;
use Sculpin\Core\Source\Filter\ChainFilter;
use Sculpin\Core\Source\Filter\DraftsFilter;
use Sculpin\Core\Source\Map\DefaultDataMap;
use Sculpin\Core\Source\Map\CalculatedDateFromFilenameMap;
use Sculpin\Core\Source\Map\DraftsMap;
use Sculpin\Core\Source\Map\ChainMap;
use Sculpin\Contrib\ProxySourceCollection\SimpleProxySourceItemFactory;
use Sculpin\Contrib\ProxySourceCollection\ProxySourceCollectionDataProvider;
use Sculpin\Contrib\Taxonomy\PermalinkStrategyCollection;
use Sculpin\Contrib\Taxonomy\PermalinkStrategyCollectionFactory;
use Sculpin\Contrib\Taxonomy\ProxySourceTaxonomyDataProvider;
use Sculpin\Contrib\Taxonomy\ProxySourceTaxonomyIndexGenerator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\String\Inflector\EnglishInflector;

/**
 * @author Kevin Boyd <kevin@whateverthing.com>
 */
class SculpinEditorExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration;
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');
    }
}
