[unreleased]

#### 2.4.1 / 2025-01-02
* update `Gitea_API::parse_asset_dir_response`

#### 2.4.0 / 2024-12-30
* update for checking contents, assets, changes, and readmes

#### 2.3.3 / 2024-12-26
* revert fix for deprecated parameter

#### 2.3.2 / 2024-12-25
* update for no parameter in `get_remote_changes()`

#### 2.3.1 / 2024-12-23
* added code as Gitea API can get repo file/folder for more efficiency

#### 2.3.0 / 2024-12-23
* updates for new Git Updater features

#### 2.2.0 / 2024-12-11
* add/update GAs
* load in `init` for `_load_textdomain_just_in_time`

#### 2.1.0 / 2024-10-31 🎃
* update GitHub Actions
* don't save primary Gitea token during remote install

#### 2.0.2 / 2023-09-10
* WPCS 3.0.0

#### 2.0.1 / 2022-11-30
* add git icon to subtab
* update GA

#### 2.0.0 / 2022-04-24
* require PHP 7.2+

#### 1.2.0 / 2021-11-15
* use new filter to add repository types to Git Updater Additions

#### 1.1.1 / 2021-09-23
* switch from `PaND` to `WP_Dismiss_Notice`

#### 1.1.0 / 2021-07-05
* updated for PHP 5.6 compatibility, will remove when WP core changes minimum requirement

#### 1.0.1 / 2021-05-21
* removed old query arg authentication
* update readme
* add language pack updating

#### 1.0.0 / 2021-05-11
* update logo branding

#### 0.9.2 / 2021-04-25
* fix option name

#### 0.9.1 / 2021-04-12
* fix PHP error, filter must return value

#### 0.9.0 / 2021-04-11
* remove branch set from constructor

#### 0.8.1 / 2021-04-05
* update assets
* update hooks

#### 0.8.0 / 2021-03-18
* update namespacing
* requires Git Updater

#### 0.7.0 / 2021-03-15 🎂
* add filter `gu_post_api_response_body`
* add filter `gu_get_git_icon_data`
* add filter `gu_parse_enterprise_headers`
* more tests added

#### 0.6.0 / 2021-03-13
* remove constructor
* update `$auth_required`
* add some tests
* add filter `gu_get_repo_api`
* add filter `gu_get_auth_header`
* add filter `gu_post_get_credentials`
* add filter `gu_parse_release_asset`

#### 0.5.0 / 2021-03-12
* de-anonymize hooks
* add filters for language pack processing

#### 0.4.0 / 2021-03-10
* add data to `gu_api_repo_type_data`
* add filter `gu_install_remote_install` for remote install
* add filter `gu_api_url_type` for API URL data

#### 0.3.1 / 2021-03-08
* update namespace

#### 0.3.0 / 2021-03-07
* update for core plugin restructuring

#### 0.2.0 / 2021-03-07
* removed the API from GitHub Updater to it's own plugin
* updated i18n
