<?php
/**
 * CacheTest.php
 *
 * The CacheTest unit test class file.
 *
 * PHP versions 5
 *
 * @author    Alexander Schneider <alexanderschneider85@gmail.com>
 * @copyright 2008-2017 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */
namespace UserAccessManager\Cache;

/**
 * Class CacheTest
 *
 * @package UserAccessManager\Cache
 */
class CacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group unit
     *
     * @return Cache
     */
    public function testCanCreateInstance()
    {
        $cache = new Cache();
        self::assertInstanceOf('\UserAccessManager\Cache\Cache', $cache);
        return $cache;
    }

    /**
     * @group   unit
     * @depends testCanCreateInstance
     * @covers  \UserAccessManager\Cache\Cache::generateCacheKey()
     *
     * @param Cache $cache
     */
    public function testGenerateCacheKey(Cache $cache)
    {
        $key = $cache->generateCacheKey(
            'preFix',
            'cacheKey',
            'postFix'
        );
        self::assertEquals('preFix|cacheKey|postFix', $key);
    }

    /**
     * @group   unit
     * @depends testCanCreateInstance
     * @covers  \UserAccessManager\Cache\Cache::addToRuntimeCache()
     *
     * @param Cache $cache
     *
     * @return Cache
     */
    public function testAddToCache(Cache $cache)
    {
        $cache->addToRuntimeCache('stringCacheKey', 'testValue');
        $cache->addToRuntimeCache('arrayCacheKey', ['testString', 'testString2']);

        self::assertAttributeEquals(
            [
                'stringCacheKey' => 'testValue',
                'arrayCacheKey' => ['testString', 'testString2']
            ],
            'runtimeCache',
            $cache
        );

        return $cache;
    }

    /**
     * @group   unit
     * @depends testAddToCache
     * @covers  \UserAccessManager\Cache\Cache::getFromRuntimeCache()
     *
     * @param Cache $cache
     *
     * @return Cache
     */
    public function testGetFromCache($cache)
    {
        self::assertEquals('testValue', $cache->getFromRuntimeCache('stringCacheKey'));
        self::assertEquals(
            ['testString', 'testString2'],
            $cache->getFromRuntimeCache('arrayCacheKey')
        );
        self::assertEquals(
            null,
            $cache->getFromRuntimeCache('notSet')
        );

        return $cache;
    }

    /**
     * @group   unit
     * @depends testAddToCache
     * @covers  \UserAccessManager\Cache\Cache::flushCache()
     *
     * @param Cache $cache
     */
    public function testFlushCache($cache)
    {
        self::assertAttributeEquals(
            [
                'stringCacheKey' => 'testValue',
                'arrayCacheKey' => ['testString', 'testString2']
            ],
            'runtimeCache',
            $cache
        );

        $cache->flushCache();

        self::assertAttributeEquals([], 'runtimeCache', $cache);
    }
}
