<?php

/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\DocumentManager\tests\Unit\Metadata;

use Sulu\Component\DocumentManager\DocumentStrategyInterface;
use Sulu\Component\DocumentManager\Metadata;
use Sulu\Component\DocumentManager\Metadata\BaseMetadataFactory;

class BaseMetadataFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->strategy = $this->prophesize(DocumentStrategyInterface::class);
        $this->factory = new BaseMetadataFactory(
            array(
                array(
                    'alias' => 'page',
                    'class' => 'Class\Page',
                    'phpcr_type' => 'sulu:page',
                ),
                array(
                    'alias' => 'snippet',
                    'class' => 'Class\Snippet',
                    'phpcr_type' => 'sulu:snippet',
                ),
            ),
            $this->strategy->reveal()
        );
    }

    /**
     * It should retrieve metadata for a fully qualified class name.
     */
    public function testGetForClass()
    {
        $metadata = $this->factory->getMetadataForClass('Class\Page');
        $this->assertInstanceOf(Metadata::class, $metadata);
        $this->assertEquals('page', $metadata->getAlias());
        $this->assertEquals('Class\Page', $metadata->getClass());
        $this->assertEquals('sulu:page', $metadata->getPhpcrType());
    }

    /**
     * It should throw an exception if there is no mapping for the class name.
     *
     * @expectedException Sulu\Component\DocumentManager\Exception\MetadataNotFoundException
     */
    public function testGetForClassNotFound()
    {
        $this->factory->getMetadataForClass('Class\Page\NotFound');
    }

    /**
     * It should retrieve metadata for a given alias.
     */
    public function testGetForAlias()
    {
        $metadata = $this->factory->getMetadataForAlias('snippet');
        $this->assertInstanceOf(Metadata::class, $metadata);
        $this->assertEquals('snippet', $metadata->getAlias());
        $this->assertEquals('Class\Snippet', $metadata->getClass());
        $this->assertEquals('sulu:snippet', $metadata->getPhpcrType());
    }

    /**
     * It should throw an exception if there is no mapping for given alias.
     *
     * @expectedException Sulu\Component\DocumentManager\Exception\MetadataNotFoundException
     */
    public function testGetForAliasNotFound()
    {
        $this->factory->getMetadataForAlias('yak');
    }

    /**
     * It should retrieve metadata for a given phpcrType.
     */
    public function testGetForPhpcrType()
    {
        $metadata = $this->factory->getMetadataForPhpcrType('sulu:snippet');
        $this->assertInstanceOf(Metadata::class, $metadata);
        $this->assertEquals('snippet', $metadata->getAlias());
        $this->assertEquals('Class\Snippet', $metadata->getClass());
        $this->assertEquals('sulu:snippet', $metadata->getPhpcrType());
    }

    public function testHasPhpcrType()
    {
        $res = $this->factory->hasMetadataForPhpcrType('sulu:snippet');
        $this->assertTrue($res);

        $res = $this->factory->hasMetadataForPhpcrType('foobar');
        $this->assertFalse($res);
    }

    /**
     * It should throw an exception if there is no mapping for given phpcrType.
     *
     * @expectedException Sulu\Component\DocumentManager\Exception\MetadataNotFoundException
     */
    public function testGetForPhpcrTypeNotFound()
    {
        $this->factory->getMetadataForPhpcrType('yak');
    }

    /**
     * It has a method to determine if an alias exists.
     */
    public function testHasAlias()
    {
        $this->assertTrue($this->factory->hasAlias('page'));
        $this->assertFalse($this->factory->hasAlias('fooabarbardg'));
    }

    /**
     * It should return a list of all registered classes
     */
    public function testGetClasses()
    {
        $classes = $this->factory->getClasses();
        $this->assertEquals(array(
            'Class\\Page',
            'Class\\Snippet',
        ), $classes);
    }
}
