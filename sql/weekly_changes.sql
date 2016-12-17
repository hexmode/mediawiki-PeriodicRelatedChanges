-- Keep track of what has been done when and for who
CREATE TABLE /*_*/weekly_changes {
  ww_user int unsigned NOT NULL,
  ww_page int unsigned NOT NULL,
  ww_timestamp binary(14)
}
