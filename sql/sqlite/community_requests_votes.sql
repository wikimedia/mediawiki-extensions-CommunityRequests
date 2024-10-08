-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/CommunityRequests/sql/community_requests_votes.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/community_requests_votes (
  crv_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  crv_request INTEGER UNSIGNED DEFAULT NULL,
  crv_focus_area INTEGER UNSIGNED DEFAULT NULL,
  crv_vote INTEGER UNSIGNED NOT NULL,
  crv_actor BIGINT UNSIGNED NOT NULL,
  crv_timestamp BLOB NOT NULL
);

CREATE INDEX crv_request ON /*_*/community_requests_votes (crv_request);

CREATE INDEX crv_focus_area ON /*_*/community_requests_votes (crv_focus_area);

CREATE UNIQUE INDEX crv_request_focus_area_actor ON /*_*/community_requests_votes (
  crv_request, crv_focus_area, crv_actor
);
