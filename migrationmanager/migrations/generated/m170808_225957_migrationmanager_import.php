<?php
namespace Craft;

/**
 * Generated migration
 */
class m170808_225957_migrationmanager_import extends BaseMigration
{
	/**
	 * Any migration code in here is wrapped inside of a transaction.
	 * Returning false will rollback the migration
	 *
	 * @return bool
	 */
	public function safeUp()
	{
	    $json = '{"settings":{"dependencies":[],"elements":[]},"content":{"entries":[{"slug":"poiup","section":"blog","locales":{"en_us":{"slug":"poiup","section":"blog","enabled":"1","locale":"en_us","localeEnabled":"1","postDate":{"date":"2017-08-07 19:22:00.000000","timezone_type":3,"timezone":"UTC"},"expiryDate":null,"title":"poiup","entryType":"blog2","body":"<p>bleh<\/p>","asset":[]},"es_us":{"slug":"poiup","section":"blog","enabled":"1","locale":"es_us","localeEnabled":"0","postDate":{"date":"2017-08-07 19:22:00.000000","timezone_type":3,"timezone":"UTC"},"expiryDate":null,"title":"poiup","entryType":"blog2","body":"<p>poiupoiuoiuop<\/p>","asset":[]},"en_ca":{"slug":"poiup","section":"blog","enabled":"1","locale":"en_ca","localeEnabled":"0","postDate":{"date":"2017-08-07 19:22:00.000000","timezone_type":3,"timezone":"UTC"},"expiryDate":null,"title":"poiup","entryType":"blog2","body":"<p>poiupoiuoiuop<\/p>","asset":[]}}}]}}';
        return craft()->migrationManager_migrations->import($json);
    }

}
