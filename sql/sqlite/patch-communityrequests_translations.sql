-- Merge wish and focus area translations into a single table (T404022)

-- Rename wishes_translations table to just 'translations'.
ALTER TABLE /*_*/communityrequests_wishes_translations RENAME TO /*_*/temp_communityrequests_translations;
CREATE TABLE /*_*/communityrequests_translations (
	crt_entity INTEGER UNSIGNED NOT NULL,
	crt_lang BLOB NOT NULL,
	crt_title BLOB NOT NULL,
	PRIMARY KEY(crt_lang, crt_entity)
);
INSERT INTO /*_*/communityrequests_translations SELECT * FROM /*_*/temp_communityrequests_translations;
DROP TABLE /*_*/temp_communityrequests_translations;

-- Merge focus area translations into translations table
INSERT INTO /*_*/communityrequests_translations (crt_entity, crt_lang, crt_title)
SELECT crfat_focus_area AS crt_entity,
	crfat_lang AS crt_lang,
	crfat_title AS crt_title
FROM /*_*/communityrequests_focus_areas_translations;

DROP TABLE /*_*/communityrequests_focus_areas_translations;
