-- Manually created patch to make use of RENAME so no data is lost.
ALTER TABLE /*_*/communityrequests_projects RENAME TO /*_*/communityrequests_tags;
