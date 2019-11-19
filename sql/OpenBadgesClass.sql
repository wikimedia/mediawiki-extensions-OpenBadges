--
-- OpenBadges logging schema
-- Records relevant information regarding issuing of badges
--

CREATE TABLE IF NOT EXISTS /*_*/openbadges_class (
  obl_badge_id int NOT NULL PRIMARY KEY auto_increment,

  -- Name of the achievement
  obl_name varchar(64) NOT NULL,

  -- Description of the badge
  obl_description blob NOT NULL,

  -- Image of the badge
  obl_badge_image varchar(255) NOT NULL,

  -- Criteria for earning the badge; might be URL
  obl_criteria varchar(255) NOT NULL,

  -- List of tags that describe the achievement
  obl_tags blob,

  -- Badge name is unique. Make this the primary key?
  UNIQUE(obl_name)
) /*$wgDBTableOptions*/;
