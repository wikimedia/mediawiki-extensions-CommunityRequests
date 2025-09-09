-- Merge wish and focus area tables into a single table (T404022)

-- Rename wishes table to entities and add type column
ALTER TABLE /*_*/communityrequests_wishes RENAME TO /*_*/communityrequests_entities;
ALTER TABLE /*_*/communityrequests_entities RENAME COLUMN cr_type TO cr_wish_type;
ALTER TABLE /*_*/communityrequests_entities ALTER COLUMN cr_wish_type DROP NOT NULL;
ALTER TABLE /*_*/communityrequests_entities ALTER COLUMN cr_actor DROP NOT NULL;
ALTER TABLE /*_*/communityrequests_entities ADD cr_entity_type SMALLINT DEFAULT 0 NOT NULL;

-- Merge focus areas into entities table
INSERT INTO /*_*/communityrequests_entities (
	cr_page,
	cr_entity_type,
	cr_status,
	cr_wish_type,
	cr_focus_area,
	cr_actor,
	cr_vote_count,
	cr_base_lang,
	cr_created,
	cr_updated
)
SELECT crfa_page AS cr_page,
	1 AS cr_entity_type,
	crfa_status AS cr_status,
	NULL AS cr_wish_type,
	NULL AS cr_focus_area,
	NULL AS cr_actor,
	crfa_vote_count AS cr_vote_count,
	crfa_base_lang AS cr_base_lang,
	crfa_created cr_created,
	crfa_updated AS cr_updated
FROM /*_*/communityrequests_focus_areas;
DROP TABLE /*_*/communityrequests_focus_areas;

-- Rename communityrequests_tags.crtg_wish to crtg_entity
ALTER TABLE /*_*/communityrequests_tags RENAME COLUMN crtg_wish TO crtg_entity;
