-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/CommunityRequests/sql/community_requests_projects.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/community_requests_projects (
  crp_id INT UNSIGNED AUTO_INCREMENT NOT NULL,
  crp_project INT UNSIGNED NOT NULL,
  crp_request INT UNSIGNED NOT NULL,
  UNIQUE INDEX crp_project_request (crp_project, crp_request),
  PRIMARY KEY(crp_id)
) /*$wgDBTableOptions*/;