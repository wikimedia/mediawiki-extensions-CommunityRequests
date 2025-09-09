-- Merge wish and focus area translations into a single table (T404022)

-- Rename wishes_translations table to just 'translations'.
ALTER TABLE /*_*/communityrequests_wishes_translations RENAME TO /*_*/communityrequests_translations;
ALTER TABLE /*_*/communityrequests_translations RENAME COLUMN crt_wish TO crt_entity;
ALTER TABLE /*_*/communityrequests_translations DROP CONSTRAINT communityrequests_wishes_translations_pkey;
ALTER TABLE /*_*/communityrequests_translations ADD PRIMARY KEY(crt_lang, crt_entity);

-- Merge focus area translations into translations table
INSERT INTO /*_*/communityrequests_translations (crt_entity, crt_lang, crt_title)
SELECT crfat_focus_area AS crt_entity,
	crfat_lang AS crt_lang,
	crfat_title AS crt_title
FROM /*_*/communityrequests_focus_areas_translations;

DROP TABLE /*_*/communityrequests_focus_areas_translations;
