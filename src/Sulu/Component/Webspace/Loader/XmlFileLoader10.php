<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\Webspace\Loader;

use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\CustomUrl;
use Sulu\Component\Webspace\Environment;
use Sulu\Component\Webspace\Exception\InvalidWebspaceException;
use Sulu\Component\Webspace\Loader\Exception\ExpectedDefaultTemplatesNotFound;
use Sulu\Component\Webspace\Loader\Exception\InvalidAmountOfDefaultErrorTemplateException;
use Sulu\Component\Webspace\Loader\Exception\InvalidCustomUrlException;
use Sulu\Component\Webspace\Loader\Exception\InvalidDefaultErrorTemplateException;
use Sulu\Component\Webspace\Loader\Exception\InvalidDefaultLocalizationException;
use Sulu\Component\Webspace\Loader\Exception\InvalidErrorTemplateException;
use Sulu\Component\Webspace\Loader\Exception\InvalidPortalDefaultLocalizationException;
use Sulu\Component\Webspace\Loader\Exception\InvalidUrlDefinitionException;
use Sulu\Component\Webspace\Loader\Exception\InvalidWebspaceDefaultLocalizationException;
use Sulu\Component\Webspace\Loader\Exception\InvalidWebspaceDefaultSegmentException;
use Sulu\Component\Webspace\Loader\Exception\PortalDefaultLocalizationNotFoundException;
use Sulu\Component\Webspace\Loader\Exception\WebspaceDefaultSegmentNotFoundException;
use Sulu\Component\Webspace\Loader\Exception\WebspaceLocalizationNotUsedException;
use Sulu\Component\Webspace\Navigation;
use Sulu\Component\Webspace\NavigationContext;
use Sulu\Component\Webspace\Portal;
use Sulu\Component\Webspace\Security;
use Sulu\Component\Webspace\Segment;
use Sulu\Component\Webspace\Url;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * This file loader is responsible for webspace configuration files in the xml format using the 1.0 version of the
 * webspace schema definition.
 * @deprecated
 */
class XmlFileLoader10 extends BaseXmlFileLoader
{
    const SCHEMA_LOCATION = '/schema/webspace/webspace-1.0.xsd';

    const SCHEMA_URI = 'http://schemas.sulu.io/webspace/webspace-1.0.xsd';

    /**
     * @var \DOMXPath
     */
    protected $xpath;

    /**
     * The webspace which is created by this file loader.
     *
     * @var Webspace
     */
    protected $webspace;

    /**
     * Loads a webspace from a xml file.
     *
     * @param mixed $resource The resource
     * @param string $type The resource type
     *
     * @return Webspace The webspace object for the given resource
     */
    public function load($resource, $type = null)
    {
        $path = $this->getLocator()->locate($resource);

        // load data in path
        return $this->parseXml($path);
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param mixed $resource A resource
     * @param string $type The resource type
     *
     * @return bool true if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        return parent::supports($resource, $type);
    }

    /**
     * Parses the entire file and returns a webspace object.
     *
     * @param $file
     *
     * @return Webspace
     */
    protected function parseXml($file)
    {
        $this->xpath = new \DOMXPath($this->tryLoad($file));
        $this->xpath->registerNamespace('x', 'http://schemas.sulu.io/webspace/webspace');

        // set simple webspace properties
        $this->webspace = new Webspace();
        $this->webspace->setName($this->xpath->query('/x:webspace/x:name')->item(0)->nodeValue);
        $this->webspace->setKey($this->xpath->query('/x:webspace/x:key')->item(0)->nodeValue);
        $this->webspace->setTheme($this->generateTheme());
        $this->webspace->setNavigation($this->generateNavigation());
        $this->webspace->setResourceLocatorStrategy('tree_leaf_edit');

        $this->generateTemplates($this->webspace);
        $this->generateDefaultTemplates($this->webspace);

        // set security
        $this->generateSecurity();

        // set localizations on webspaces
        $this->generateWebspaceLocalizations();

        // set segments on webspaces
        $this->generateSegments();

        // set portals on webspaces
        $this->generatePortals();

        // validate the webspace, and throw exceptions if not valid
        $this->validate();

        return $this->webspace;
    }

    /**
     * Returns xml-doc when one scheme matches.
     *
     * @param string $file
     *
     * @return \DOMDocument
     *
     * @throws InvalidWebspaceException
     */
    protected function tryLoad($file)
    {
        try {
            return XmlUtils::loadFile($file, __DIR__ . static::SCHEMA_LOCATION);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidWebspaceException(
                sprintf(
                    'Could not parse webspace XML file "%s"',
                    $file
                ),
                null,
                $e
            );
        }
    }

    /**
     * Validate result.
     */
    protected function validate()
    {
        $this->validateWebspaceDefaultLocalization();
        $this->validateDefaultPortalLocalization();
        $this->validateWebspaceDefaultSegment();
        $this->validateLocalizations();
    }

    /**
     * Sets the default localization for the given portal.
     *
     * @param $portal Portal
     *
     * @return bool True when successful, otherwise false
     */
    protected function loadPortalLocalizationDefaultFromWebspace($portal)
    {
        $webspaceDefaultLocalization = $this->webspace->getDefaultLocalization();

        foreach ($portal->getLocalizations() as $localization) {
            if ($webspaceDefaultLocalization
                && $webspaceDefaultLocalization->getLocalization() == $localization->getLocalization()
            ) {
                $localization->setDefault(true);
                $portal->setDefaultLocalization($localization);

                return true;
            }
        }

        return false;
    }

    /**
     * Generates all localizations for the given portal.
     *
     * @param \DOMNode $portalNode
     * @param Portal $portal
     */
    protected function generatePortalLocalizations(\DOMNode $portalNode, Portal $portal)
    {
        if ($this->xpath->query('x:localizations', $portalNode)->length > 0) {
            // set localizations from portal, if they are set
            $localizationNodes = $this->xpath->query('x:localizations/x:localization', $portalNode);
            $this->generateLocalizationsFromNodeList($localizationNodes, $portal);
        } else {
            // if the portal has no localizations fallback to the localizations from the webspace
            $localizationNodes = $this->xpath->query('/x:webspace/x:localizations//x:localization');
            $this->generateLocalizationsFromNodeList($localizationNodes, $portal, true);
        }
    }

    /**
     * Generates the localizations for the given portal from the given DOMNodeList.
     *
     * @param \DOMNodeList $localizationNodes
     * @param Portal $portal
     * @param bool $flat
     */
    protected function generateLocalizationsFromNodeList(\DOMNodeList $localizationNodes, Portal $portal, $flat = false)
    {
        foreach ($localizationNodes as $localizationNode) {
            $localization = $this->generateLocalizationFromNode($localizationNode, $flat);

            $portal->addLocalization($localization);
        }
    }

    /**
     * Generates a localization from the given node.
     *
     * @param \DOMElement|\DOMNode $localizationNode
     * @param bool $flat
     * @param null $parent
     *
     * @return Localization
     */
    protected function generateLocalizationFromNode(\DOMElement $localizationNode, $flat = false, $parent = null)
    {
        $localization = new Localization();
        $localization->setLanguage($localizationNode->attributes->getNamedItem('language')->nodeValue);

        // set parent if given
        if ($parent) {
            $localization->setParent($parent);
        }

        // set optional nodes
        $countryNode = $localizationNode->attributes->getNamedItem('country');
        if ($countryNode) {
            $localization->setCountry($countryNode->nodeValue);
        }

        $shadowNode = $localizationNode->attributes->getNamedItem('shadow');
        if ($shadowNode) {
            $localization->setShadow($shadowNode->nodeValue);
        }

        $defaultNode = $localizationNode->attributes->getNamedItem('default');
        if ($defaultNode) {
            $localization->setDefault($defaultNode->nodeValue == 'true');
        } else {
            $localization->setDefault(false);
        }

        $xDefaultNode = $localizationNode->attributes->getNamedItem('x-default');
        if ($xDefaultNode) {
            $localization->setXDefault($xDefaultNode->nodeValue == 'true');
        } else {
            $localization->setXDefault(false);
        }

        // set child nodes
        if (!$flat) {
            foreach ($this->xpath->query('x:localization', $localizationNode) as $childNode) {
                $localization->addChild($this->generateLocalizationFromNode($childNode, $flat, $localization));
            }
        }

        return $localization;
    }

    /**
     * Generates and sets the security object from the XML document.
     */
    protected function generateSecurity()
    {
        $securitySystemNode = $this->xpath->query('/x:webspace/x:security/x:system');
        if ($securitySystemNode->length > 0) {
            $security = new Security();
            $security->setSystem($securitySystemNode->item(0)->nodeValue);
            $this->webspace->setSecurity($security);
        }
    }

    /**
     * Generates the localization for the webspace from the XML document.
     */
    protected function generateWebspaceLocalizations()
    {
        foreach ($this->xpath->query('/x:webspace/x:localizations/x:localization') as $localizationNode) {
            $localization = $this->generateLocalizationFromNode($localizationNode);

            $this->webspace->addLocalization($localization);
        }
    }

    /**
     * Generates the available segments for the webspace from the XML document.
     */
    protected function generateSegments()
    {
        foreach ($this->xpath->query('/x:webspace/x:segments/x:segment') as $segmentNode) {
            /** @var \DOMNode $segmentNode */
            $segment = new Segment();
            $segment->setName($segmentNode->nodeValue);
            $segment->setKey($segmentNode->attributes->getNamedItem('key')->nodeValue);

            $defaultNode = $segmentNode->attributes->getNamedItem('default');
            if ($defaultNode) {
                $segment->setDefault($defaultNode->nodeValue == 'true');
            } else {
                $segment->setDefault(false);
            }

            $this->webspace->addSegment($segment);
        }
    }

    /**
     * Generate all the portals for the webspace.
     */
    protected function generatePortals()
    {
        foreach ($this->xpath->query('/x:webspace/x:portals/x:portal') as $portalNode) {
            /** @var \DOMNode $portalNode */
            $portal = new Portal();

            $portal->setName($this->xpath->query('x:name', $portalNode)->item(0)->nodeValue);
            $portal->setKey($this->xpath->query('x:key', $portalNode)->item(0)->nodeValue);

            // set localization on portal
            $this->generatePortalLocalizations($portalNode, $portal);

            $this->webspace->addPortal($portal);
            $portal->setWebspace($this->webspace);

            // set environments
            $this->generateEnvironments($portalNode, $portal);
        }
    }

    /**
     * Generates the theme for the webspace.
     *
     * @return string
     */
    protected function generateTheme()
    {
        $nodes = $this->xpath->query('/x:webspace/x:theme/x:key');
        if ($nodes->length > 0) {
            return $nodes->item(0)->nodeValue;
        }

        $nodes = $this->xpath->query('/x:webspace/x:theme');
        if ($nodes->length === 0) {
            return;
        }

        return $nodes->item(0)->nodeValue;
    }

    /**
     * Generates the available template types for the given webspace.
     *
     * @param Webspace $webspace
     *
     * @return Webspace
     *
     * @throws InvalidAmountOfDefaultErrorTemplateException
     * @throws InvalidDefaultErrorTemplateException
     * @throws InvalidErrorTemplateException
     */
    protected function generateTemplates(Webspace $webspace)
    {
        $defaultErrorTemplates = 0;

        foreach ($this->xpath->query('/x:webspace/x:theme/x:error-templates/x:error-template') as $errorTemplateNode) {
            /* @var \DOMNode $errorTemplateNode */
            $template = $errorTemplateNode->nodeValue;
            if (($codeNode = $errorTemplateNode->attributes->getNamedItem('code')) !== null) {
                $webspace->addTemplate('error-' . $codeNode->nodeValue, $template);
            } elseif (($defaultNode = $errorTemplateNode->attributes->getNamedItem('default')) !== null) {
                $default = $defaultNode->nodeValue === 'true';
                if (!$default) {
                    throw new InvalidDefaultErrorTemplateException($template, $this->webspace->getKey());
                }
                ++$defaultErrorTemplates;
                $webspace->addTemplate('error', $template);
            } else {
                throw new InvalidErrorTemplateException($template, $this->webspace->getKey());
            }
        }

        // only one or none default error-template is legal
        if ($defaultErrorTemplates > 1) {
            throw new InvalidAmountOfDefaultErrorTemplateException($this->webspace->getKey());
        }

        return $webspace;
    }

    /**
     * Generates the default templates for the webspace.
     *
     * @param Webspace $webspace
     *
     * @return Webspace
     *
     * @throws ExpectedDefaultTemplatesNotFound
     */
    protected function generateDefaultTemplates(Webspace $webspace)
    {
        $expected = ['page', 'home'];

        foreach ($this->xpath->query('/x:webspace/x:theme/x:default-templates/x:default-template') as $node) {
            /* @var \DOMNode $node */
            $template = $node->nodeValue;
            $type = $node->attributes->getNamedItem('type')->nodeValue;

            $webspace->addDefaultTemplate($type, $template);
            if ($type === 'homepage') {
                $webspace->addDefaultTemplate('home', $template);
            }
        }

        $found = array_keys($webspace->getDefaultTemplates());
        foreach ($expected as $item) {
            if (!in_array($item, $found)) {
                throw new ExpectedDefaultTemplatesNotFound($this->webspace->getKey(), $expected, $found);
            }
        }

        return $webspace;
    }

    /**
     * Generates the availabel navigation contexts for the webspace.
     *
     * @return Navigation
     */
    protected function generateNavigation()
    {
        $contexts = [];

        foreach ($this->xpath->query('/x:webspace/x:navigation/x:contexts/x:context') as $contextNode) {
            /* @var \DOMNode $contextNode */
            $contexts[] = new NavigationContext(
                $contextNode->attributes->getNamedItem('key')->nodeValue,
                $this->loadMeta('x:meta/x:*', $contextNode)
            );
        }

        return new Navigation($contexts);
    }

    /**
     * Loads the meta information like a translatable title from the webspace.
     *
     * @param $path
     * @param \DOMNode|null $context
     *
     * @return array
     */
    protected function loadMeta($path, \DOMNode $context = null)
    {
        $result = [];

        /** @var \DOMElement $node */
        foreach ($this->xpath->query($path, $context) as $node) {
            $attribute = $node->tagName;
            $lang = $this->xpath->query('@lang', $node)->item(0)->nodeValue;

            if (!isset($result[$node->tagName])) {
                $result[$attribute] = [];
            }
            $result[$attribute][$lang] = $node->textContent;
        }

        return $result;
    }

    /**
     * Generates the definitions for the available environments for this webspace.
     *
     * @param \DOMNode $portalNode
     * @param Portal $portal
     */
    protected function generateEnvironments(\DOMNode $portalNode, Portal $portal)
    {
        foreach ($this->xpath->query('x:environments/x:environment', $portalNode) as $environmentNode) {
            /** @var \DOMNode $environmentNode */
            $environment = new Environment();
            $environment->setType($environmentNode->attributes->getNamedItem('type')->nodeValue);

            $this->generateUrls($environmentNode, $environment);
            $this->generateCustomUrls($environmentNode, $environment);

            $portal->addEnvironment($environment);
        }
    }

    /**
     * Generates the URLs for the given environment.
     *
     * @param \DOMNode $environmentNode
     * @param Environment $environment
     *
     * @throws Exception\InvalidUrlDefinitionException
     */
    protected function generateUrls(\DOMNode $environmentNode, Environment $environment)
    {
        foreach ($this->xpath->query('x:urls/x:url', $environmentNode) as $urlNode) {
            // check if the url is valid, and throw an exception otherwise
            if (!$this->checkUrlNode($urlNode)) {
                throw new InvalidUrlDefinitionException($this->webspace, $urlNode->nodeValue);
            }

            /** @var \DOMNode $urlNode */
            $url = new Url();

            $url->setUrl(rtrim($urlNode->nodeValue, '/'));

            // set optional nodes
            $url->setLanguage($this->getOptionalNodeAttribute($urlNode, 'language'));
            $url->setCountry($this->getOptionalNodeAttribute($urlNode, 'country'));
            $url->setSegment($this->getOptionalNodeAttribute($urlNode, 'segment'));
            $url->setRedirect($this->getOptionalNodeAttribute($urlNode, 'redirect'));
            $url->setMain($this->getOptionalNodeAttribute($urlNode, 'main', false));
            $url->setAnalyticsKey($this->getOptionalNodeAttribute($urlNode, 'analytics-key'));

            $environment->addUrl($url);
        }
    }

    /**
     * Generates the custom URLs from the XML document.
     *
     * A custom URL must contain at lease one *, which will be used as a placeholder.
     *
     * @param \DOMNode $environmentNode
     * @param Environment $environment
     *
     * @throws InvalidCustomUrlException
     */
    protected function generateCustomUrls(\DOMNode $environmentNode, Environment $environment)
    {
        foreach ($this->xpath->query('x:custom-urls/x:custom-url', $environmentNode) as $urlNode) {
            /** @var \DOMNode $urlNode */
            $url = new CustomUrl();

            $url->setUrl(rtrim($urlNode->nodeValue, '/'));

            if (false === strpos($url->getUrl(), '*')) {
                throw new InvalidCustomUrlException($this->webspace, $url->getUrl());
            }

            $environment->addCustomUrl($url);
        }
    }

    /**
     * Returns an optional value from the given node. The default value will be used if the node does not exist.
     *
     * @param \DOMNode $node
     * @param string $name
     * @param mixed $default
     *
     * @return mixed
     */
    protected function getOptionalNodeAttribute(\DOMNode $node, $name, $default = null)
    {
        $attribute = $node->attributes->getNamedItem($name);
        if ($attribute) {
            return $attribute->nodeValue;
        }

        return $default;
    }

    /**
     * Checks if the urlNode is valid for this webspace.
     *
     * @param \DOMNode $urlNode
     *
     * @return bool
     */
    protected function checkUrlNode(\DOMNode $urlNode)
    {
        $hasLocalization = ($urlNode->attributes->getNamedItem('localization') != null)
            || (strpos($urlNode->nodeValue, '{localization}') !== false);

        $hasLanguage = ($urlNode->attributes->getNamedItem('language') != null)
            || (strpos($urlNode->nodeValue, '{language}') !== false)
            || $hasLocalization;

        $hasSegment = (count($this->webspace->getSegments()) == 0)
            || ($urlNode->attributes->getNamedItem('segment') != null)
            || (strpos($urlNode->nodeValue, '{segment}') !== false);

        $hasRedirect = ($urlNode->attributes->getNamedItem('redirect') != null);

        return ($hasLanguage && $hasSegment) || $hasRedirect;
    }

    /**
     * Validate default webspace localization.
     *
     * @throws Exception\InvalidWebspaceDefaultLocalizationException
     */
    protected function validateWebspaceDefaultLocalization()
    {
        try {
            $this->validateDefaultLocalization($this->webspace->getLocalizations());
        } catch (InvalidDefaultLocalizationException $ex) {
            throw new InvalidWebspaceDefaultLocalizationException($this->webspace);
        }
    }

    /**
     * Validate portal localization.
     *
     * @throws Exception\PortalDefaultLocalizationNotFoundException
     * @throws Exception\InvalidPortalDefaultLocalizationException
     */
    protected function validateDefaultPortalLocalization()
    {
        // check all portal localizations
        foreach ($this->webspace->getPortals() as $portal) {
            try {
                if (!$this->validateDefaultLocalization($portal->getLocalizations())) {
                    // try to load the webspace localizations before throwing an exception
                    if (!$this->loadPortalLocalizationDefaultFromWebspace($portal)) {
                        throw new PortalDefaultLocalizationNotFoundException($this->webspace, $portal);
                    }
                }
            } catch (InvalidDefaultLocalizationException $ex) {
                throw new InvalidPortalDefaultLocalizationException($this->webspace, $portal);
            }
        }
    }

    /**
     * Validate that all localizations are used in the portals.
     *
     * @throws Exception\PortalDefaultLocalizationNotFoundException
     * @throws Exception\InvalidPortalDefaultLocalizationException
     */
    protected function validateLocalizations()
    {
        $locales = array_unique(
            array_map(
                function (Localization $localization) {
                    return $localization->getLocale(Localization::UNDERSCORE);
                },
                $this->webspace->getAllLocalizations()
            )
        );

        $portalLocales = [];
        foreach ($this->webspace->getPortals() as $portal) {
            $portalLocales = array_merge(
                $portalLocales,
                array_map(
                    function (Localization $localization) {
                        return $localization->getLocale(Localization::UNDERSCORE);
                    },
                    $portal->getLocalizations()
                )
            );
        }

        $portalLocales = array_unique($portalLocales);

        if (array_diff($locales, $portalLocales) || array_diff($portalLocales, $locales)) {
            throw new WebspaceLocalizationNotUsedException($this->webspace);
        }
    }

    /**
     * Validate webspace default segment.
     *
     * @throws Exception\WebspaceDefaultSegmentNotFoundException
     * @throws Exception\InvalidWebspaceDefaultSegmentException
     */
    protected function validateWebspaceDefaultSegment()
    {
        // check if there are duplicate defaults in the webspaces segments
        $segments = $this->webspace->getSegments();
        if ($segments) {
            $webspaceDefaultSegmentFound = false;
            foreach ($segments as $webspaceSegment) {
                if ($webspaceSegment->isDefault()) {
                    // throw an exception, if a new default segment is found, although there already is one
                    if ($webspaceDefaultSegmentFound) {
                        throw new InvalidWebspaceDefaultSegmentException($this->webspace);
                    }
                    $webspaceDefaultSegmentFound = true;
                }
            }

            if (!$webspaceDefaultSegmentFound) {
                throw new WebspaceDefaultSegmentNotFoundException($this->webspace);
            }
        }
    }

    /**
     * Returns true if there is one default localization.
     *
     * @param $localizations
     *
     * @return bool
     *
     * @throws Exception\InvalidDefaultLocalizationException
     */
    protected function validateDefaultLocalization($localizations)
    {
        $result = false;
        foreach ($localizations as $localization) {
            if ($localization->isDefault()) {
                if ($result) {
                    throw new InvalidDefaultLocalizationException();
                }
                $result = true;
            }
        }

        return $result;
    }
}
