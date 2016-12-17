-- Keep track of what has been done when and for who
CREATE TABLE /*_*/weekly_changes (
  wc_user int unsigned NOT NULL,
  wc_page int unsigned NOT NULL,
  wc_timestamp binary(14) default NULL
) /*$wgDBTableOptions*/;
