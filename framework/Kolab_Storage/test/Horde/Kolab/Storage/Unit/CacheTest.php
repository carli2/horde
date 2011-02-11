<?php
/**
 * Test the Kolab cache.
 *
 * PHP version 5
 *
 * @category Kolab
 * @package  Kolab_Storage
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Kolab_Storage
 */

/**
 * Prepare the test setup.
 */
require_once dirname(__FILE__) . '/../Autoload.php';

/**
 * Test the Kolab cache.
 *
 * Copyright 2008-2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @category Kolab
 * @package  Kolab_Storage
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Kolab_Storage
 */
class Horde_Kolab_Storage_Unit_CacheTest
extends Horde_Kolab_Storage_TestCase
{
    public function setUp()
    {
        $this->cache = new Horde_Kolab_Storage_Cache(
            new Horde_Cache(
                new Horde_Cache_Storage_Mock()
            )
        );
    }

    public function testGetDataCache()
    {
        $this->assertInstanceOf(
            'Horde_Kolab_Storage_Cache_Data',
            $this->cache->getDataCache($this->_getDataParameters())
        );
    }

    public function testCachedDataCache()
    {
        $this->assertSame(
            $this->cache->getDataCache($this->_getDataParameters()),
            $this->cache->getDataCache($this->_getDataParameters())
        );
    }

    public function testNewHostNewData()
    {
        $params = $this->_getDataParameters();
        $params['host'] = 'b';
        $this->assertNotSame(
            $this->cache->getDataCache($params),
            $this->cache->getDataCache($this->_getDataParameters())
        );
    }

    /**
     * @expectedException Horde_Kolab_Storage_Exception
     */
    public function testDataMissingHost()
    {
        $params = $this->_getDataParameters();
        unset($params['host']);
        $this->cache->getDataCache($params);
    }

    public function testNewPortNewData()
    {
        $params = $this->_getDataParameters();
        $params['port'] = 2;
        $this->assertNotSame(
            $this->cache->getDataCache($params),
            $this->cache->getDataCache($this->_getDataParameters())
        );
    }

    /**
     * @expectedException Horde_Kolab_Storage_Exception
     */
    public function testDataMissingPort()
    {
        $params = $this->_getDataParameters();
        unset($params['port']);
        $this->cache->getDataCache($params);
    }

    public function testNewFolderNewData()
    {
        $params = $this->_getDataParameters();
        $params['folder'] = 'J';
        $this->assertNotSame(
            $this->cache->getDataCache($params),
            $this->cache->getDataCache($this->_getDataParameters())
        );
    }

    /**
     * @expectedException Horde_Kolab_Storage_Exception
     */
    public function testDataMissingFolder()
    {
        $params = $this->_getDataParameters();
        unset($params['folder']);
        $this->cache->getDataCache($params);
    }

    public function testNewTypeNewData()
    {
        $params = $this->_getDataParameters();
        $params['type'] = 'f';
        $this->assertNotSame(
            $this->cache->getDataCache($params),
            $this->cache->getDataCache($this->_getDataParameters())
        );
    }

    /**
     * @expectedException Horde_Kolab_Storage_Exception
     */
    public function testDataMissingType()
    {
        $params = $this->_getDataParameters();
        unset($params['type']);
        $this->cache->getDataCache($params);
    }

    public function testGetListCache()
    {
        $this->assertInstanceOf(
            'Horde_Kolab_Storage_Cache_List',
            $this->cache->getListCache($this->_getConnectionParameters())
        );
    }

    public function testCachedListCache()
    {
        $this->assertSame(
            $this->cache->getListCache($this->_getConnectionParameters()),
            $this->cache->getListCache($this->_getConnectionParameters())
        );
    }

    public function testNewHost()
    {
        $this->assertNotSame(
            $this->cache->getListCache(
                array('host' => 'b', 'port' => 1, 'user' => 'x')
            ),
            $this->cache->getListCache($this->_getConnectionParameters())
        );
    }

    public function testNewPort()
    {
        $this->assertNotSame(
            $this->cache->getListCache(
                array('host' => 'a', 'port' => 2, 'user' => 'x')
            ),
            $this->cache->getListCache($this->_getConnectionParameters())
        );
    }

    public function testNewUser()
    {
        $this->assertNotSame(
            $this->cache->getListCache(
                array('host' => 'a', 'port' => 1, 'user' => 'y')
            ),
            $this->cache->getListCache($this->_getConnectionParameters())
        );
    }

    /**
     * @expectedException Horde_Kolab_Storage_Exception
     */
    public function testMissingHost()
    {
        $this->cache->getListCache(array('port' => 1, 'user' => 'x'));
    }

    /**
     * @expectedException Horde_Kolab_Storage_Exception
     */
    public function testMissingPort()
    {
        $this->cache->getListCache(array('host' => 'a', 'user' => 'x'));
    }

    /**
     * @expectedException Horde_Kolab_Storage_Exception
     */
    public function testMissingUser()
    {
        $this->cache->getListCache(array('host' => 'a', 'port' => 1));
    }

    public function testLoadList()
    {
        $this->assertFalse(
            $this->cache->loadList('test')
        );
    }

    public function testStoreList()
    {
        $this->cache->storeList('test', true);
        $this->assertTrue(
            $this->cache->loadList('test')
        );
    }

    private function _getConnectionParameters()
    {
        return array('host' => 'a', 'port' => 1, 'user' => 'x');
    }

    private function _getDataParameters()
    {
        return array(
            'host' => 'a', 'port' => 1, 'folder' => 'I', 'type' => 'e'
        );
    }
}
