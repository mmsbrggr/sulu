<?php

use Sulu\Component\Content\Property;

/**
 * Template_Structure_Template
 *
 * DO NOT EDIT
 * This file is autogenerated by the Sulu Content component
 */
class Template_Structure_Template extends Sulu\Component\Content\Structure
{
    public function __construct()
    {
        parent::__construct('template', 'page.html.twig', 'SuluContentBundle:Default:index', '2400');

        $this->add(
            new Property(
                'title',
                'text_line',
                true,
                false,
                1,
                1,
                array()
            )
        );

        $this->add(
            new Property(
                'url',
                'resource_locator',
                true,
                false,
                1,
                1,
                array()
            )
        );

        $this->add(
            new Property(
                'article',
                'text_area',
                false,
                false,
                1,
                1,
                array()
            )
        );

        $this->add(
            new Property(
                'pages',
                'smart_content_selection',
                false,
                false,
                1,
                1,
                array()
            )
        );

        $this->add(
            new Property(
                'images',
                'image_selection',
                false,
                false,
                2,
                0,
                array(
                    'minLinks' => '1',
                    'maxLinks' => '10',
                )
            )
        );
    }
}
