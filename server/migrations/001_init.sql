-- Stillroom CMS schema v1.
-- One file for both MySQL/MariaDB and SQLite: {PK} {INT} {STR} {TEXT} {OPTS} are replaced per driver by Migrator.
-- Draftable content (tracks, collections, settings) keeps the live values in columns and pending edits in `draft` (JSON).

CREATE TABLE users (
  id {PK},
  email {STR} NOT NULL,
  name {STR} NOT NULL DEFAULT '',
  password_hash {STR} NOT NULL,
  role {STR} NOT NULL DEFAULT 'editor',
  active {INT} NOT NULL DEFAULT 1,
  created_at {STR} NOT NULL,
  last_login_at {STR} NULL,
  UNIQUE (email)
){OPTS};

CREATE TABLE login_attempts (
  id {PK},
  ip {STR} NOT NULL,
  email {STR} NOT NULL DEFAULT '',
  success {INT} NOT NULL DEFAULT 0,
  created_at {STR} NOT NULL
){OPTS};
CREATE INDEX idx_login_attempts_ip ON login_attempts (ip, created_at);

CREATE TABLE media (
  id {PK},
  kind {STR} NOT NULL,
  path {STR} NOT NULL,
  original_name {STR} NOT NULL DEFAULT '',
  mime {STR} NOT NULL,
  size {INT} NOT NULL DEFAULT 0,
  width {INT} NULL,
  height {INT} NULL,
  duration {INT} NULL,
  created_by {INT} NULL,
  created_at {STR} NOT NULL
){OPTS};

CREATE TABLE categories (
  id {PK},
  slug {STR} NOT NULL,
  title {STR} NOT NULL,
  intro {STR} NOT NULL DEFAULT '',
  description {TEXT} NULL,
  sort {INT} NOT NULL DEFAULT 0,
  visible {INT} NOT NULL DEFAULT 1,
  created_at {STR} NOT NULL,
  updated_at {STR} NOT NULL,
  UNIQUE (slug)
){OPTS};

CREATE TABLE tags (
  id {PK},
  slug {STR} NOT NULL,
  title {STR} NOT NULL,
  created_at {STR} NOT NULL,
  UNIQUE (slug)
){OPTS};

CREATE TABLE tracks (
  id {PK},
  slug {STR} NOT NULL,
  status {STR} NOT NULL DEFAULT 'draft',
  title {STR} NOT NULL,
  subtitle {STR} NOT NULL DEFAULT '',
  details {STR} NOT NULL DEFAULT '',
  description {TEXT} NULL,
  category_id {INT} NULL,
  scene {STR} NOT NULL DEFAULT 'stress-relief',
  engine {STR} NULL,
  audio_media_id {INT} NULL,
  cover_media_id {INT} NULL,
  duration {INT} NULL,
  sort {INT} NOT NULL DEFAULT 0,
  featured {INT} NOT NULL DEFAULT 0,
  seo_title {STR} NOT NULL DEFAULT '',
  seo_description {TEXT} NULL,
  draft {TEXT} NULL,
  created_by {INT} NULL,
  created_at {STR} NOT NULL,
  updated_at {STR} NOT NULL,
  published_at {STR} NULL,
  UNIQUE (slug)
){OPTS};
CREATE INDEX idx_tracks_status ON tracks (status, sort);

CREATE TABLE track_tags (
  track_id {INT} NOT NULL,
  tag_id {INT} NOT NULL,
  PRIMARY KEY (track_id, tag_id)
){OPTS};

CREATE TABLE collections (
  id {PK},
  slug {STR} NOT NULL,
  status {STR} NOT NULL DEFAULT 'draft',
  title {STR} NOT NULL,
  description {TEXT} NULL,
  cover_media_id {INT} NULL,
  sort {INT} NOT NULL DEFAULT 0,
  draft {TEXT} NULL,
  created_at {STR} NOT NULL,
  updated_at {STR} NOT NULL,
  published_at {STR} NULL,
  UNIQUE (slug)
){OPTS};

CREATE TABLE collection_tracks (
  collection_id {INT} NOT NULL,
  track_id {INT} NOT NULL,
  sort {INT} NOT NULL DEFAULT 0,
  PRIMARY KEY (collection_id, track_id)
){OPTS};

CREATE TABLE settings (
  name {STR} NOT NULL,
  published {TEXT} NULL,
  draft {TEXT} NULL,
  updated_at {STR} NOT NULL,
  published_at {STR} NULL,
  PRIMARY KEY (name)
){OPTS};

CREATE TABLE audit_log (
  id {PK},
  user_id {INT} NULL,
  action {STR} NOT NULL,
  entity {STR} NOT NULL DEFAULT '',
  entity_id {STR} NOT NULL DEFAULT '',
  detail {STR} NOT NULL DEFAULT '',
  created_at {STR} NOT NULL
){OPTS};
CREATE INDEX idx_audit_created ON audit_log (created_at);
