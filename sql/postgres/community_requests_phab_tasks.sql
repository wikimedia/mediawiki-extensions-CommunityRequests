-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/CommunityRequests/sql/community_requests_phab_tasks.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE community_requests_phab_tasks (
  crpt_id SERIAL NOT NULL,
  crpt_task_id INT NOT NULL,
  crpt_request INT NOT NULL,
  PRIMARY KEY(crpt_id)
);

CREATE INDEX crpt_task_request ON community_requests_phab_tasks (crpt_task_id, crpt_request);