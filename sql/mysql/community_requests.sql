-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/CommunityRequests/sql/community_requests.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/community_requests (
  cr_page INT UNSIGNED NOT NULL,
  cr_status INT UNSIGNED NOT NULL,
  cr_type INT UNSIGNED NOT NULL,
  cr_title VARBINARY(255) NOT NULL,
  cr_audience VARBINARY(255) DEFAULT NULL,
  cr_other_project VARBINARY(255) DEFAULT NULL,
  cr_focus_area INT UNSIGNED DEFAULT NULL,
  cr_actor BIGINT UNSIGNED NOT NULL,
  cr_timestamp BINARY(14) NOT NULL,
  INDEX cr_timestamp (cr_timestamp),
  INDEX cr_title (cr_title),
  UNIQUE INDEX cr_page (cr_page),
  PRIMARY KEY(cr_page)
) /*$wgDBTableOptions*/;
