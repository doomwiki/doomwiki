# DoomWiki (doomwiki.org) :: Mediawiki Site
This is the official DOOM Wiki (https://doomwiki.org). a containerized Mediawiki application stack and installation.

## Getting Started

These instructions will get a containerized LAMP stack and the Doom Wiki site running locally for evaluation and experimentation. Additional instructions to deploy with this project are addressed in other sections.

### Prerequisites

- Local Docker installation, such as [Docker Desktop](https://www.docker.com/products/docker-desktop/). 
- A command-line shell such as [Git-Bash](https://gitforwindows.org/)
- (Recommended) [VSCode](https://code.visualstudio.com/) as local IDE

### Installation

1. Clone the repo
1. Copy `.env.example` to `.env` to establish local environment variables. For local testing the default values will work fine, though you may want to customize `COMPOSE_PROJECT_NAME` if using multiple local versions of this template. This will ensure related Docker containers can easily be grouped and differentiated by project name.
1. Some Windows Docker Desktop installations require explicit sharing access to the folders on your local drive that are used by containers. To address this navigate to the Docker Desktop settings (gear icon) > "Resources" > "File Sharing" and add your cloned project repo directory to the list. Be sure to click "Apply and Restart". If you have multiple project repos using containers it may be helpful to add a common parent directory to this list instead of adding each repo's directory indivudally. If you installed Docker Desktop with WSL you may not see this "Resources > File Sharing" option and can simply skip this step. See Docker's [File Sharing](https://docs.docker.com/desktop/settings/windows/#file-sharing) notes for more.

### Running the Application

1. Ensure the Docker service is running. Starting Docker Desktop will start all needed services.
1. At the root of the project, start the application via the command-line in "detached" mode:
   ```bash
   docker compose up -d
   ```
1. On the first run the web host container and database container need to be provisioned from scratch, so it will take some time for everything to build. Once the related Docker progress output finishes, also allow a couple additional minuets for composer operations to run and the server daemons to start before proceeding. Note that future startups will leverage caching and be very quick.
1. Navigate to the site's URL at:
   ```
   http://localhost:8080
   ```
   You should see the Doom Wiki website.


You can stop, restart and even rebuild the application at any time, and the database and content files will persist:

```bash
# Stop the application
docker compose stop
# Restart the application
docker compose up -d
# Rebuild the application (if changing infrastructure files)
docker compose up -d --build
```

To start over there's no need to re-clone the project. Simply remove the the containers and database volume before restarting the application:

```bash
# Remove containers
docker compose down
# Remove persistent DB volume, substituting "base" with project name
docker volume rm base_db
# Optionally also remove any content files
rm -Rf ./public_html/images/*
# Restart
docker compose up -d
```

After this you will be back to the start of this "Running the Application" section.

## Deploying the Project

Note that:

- The environment `.env` file used locally has no impact on deployed environments. The deployment pipline will dynamically generate an `.env` file that's appropriate for each AWS environment automatically.
- When running the application locally, you are running a version that is designed for development. While this version uses the same core environment and stack software versions, there are some minor differences in the way code directories are mapped to the container to support development. You can however simulate the deploy environment locally with the following command:
  ```
  docker compose --profile deploy up -d
  ```
  This will create an _additional_ container, accessible via `http://localhost:8081` that builds and runs exactly the way the deployed environment will run. This can be useful for pre-deploy testing.



When an environment is running in AWS it will depend on secrets that are defined in AWS account parameters for a variety of services. The localsettings.php and config-generator.sh files have been setup to handle common parameters from AWS and get them securely passed to the application. Support has been included for things like SES/SMTP and TFA but these files can further be customized for projects that depend on other special services. For common cases the following AWS parameters should be defined (in addition to secrets already managed by the infrastructure template):

- **WikiConfOverrides**. A json object representing environment-specific configuration overrides specifically for SES/SMTP and MediaWiki. For example:
  ```json
  {
    "smtp_settings": {
      "smtp_host": "email-smtp.us-east-1.amazonaws.com",
      "smtp_port": "587",
      "smtp_username": "****",
      "smtp_password": "****"
    },
    "wiki_secret_key": "****",
    "wiki_google_site_verification": "****",
    "wiki_monaco_paypal_id": "****",
    "wiki_ub_upload_blacklist": "****"
  }
  ```

## Developing in the Container

The local container environment is setup to support development needs. Containers are designed to be [immutable](https://cloud.google.com/architecture/best-practices-for-operating-containers#immutability) but some exceptions are made for the local development environment such that the various application sources are dynamically bind-mounted into the container-provided LAMP stack. This means that most application source code can be modified both inside and outside the container (e.g. in an IDE) and the changes will be reflected immediately in the running environment without a rebuild. Changes can then be easily tested and committed. This only applies to the local development environment, as both the LAMP stack and the MediaWiki application are immutable in any deployed environments (i.e. staging and production).

See `services > drupal_dev > volumes` inside `docker-compose.yml` for a full list of paths that are bind-mounted into the container. Note that the entire MediaWiki code base is not bind-mounted, only the folders that contain custom code. This is done for performance reasons as read/write operations across a bind-mount, specifically between differing file systems like Linux and NTFS, are slow. The most noteworthy exclusions from the bind-mount list are the folder locations that are managed by composer, as these should not be modified directly or committed to the repo, and can therefore be installed inside the container only.

### Workflow Recommendations

When adapting a development workflow for a containerized environment it can be helpful to identify which tasks are best accomplished inside the container (via Docker Desktop terminal connection) vs. outside the container (via an IDE like VSCode or local git-bash shell). This is a general guide for reference purposes.

| Task                    | Where to run      | Notes                                                                                                                                                                               |
| ----------------------- | ----------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Git Operations          | Outside Container | Git is only available outside the container.                                                                                                                                        |
| Composer Operations     | Inside Container  | Folders managed by composer are not bind-mounted and only available inside container. However, composer.json and composer.lock are bind-mounted and can be committed.               |
| MediaWiki Operations    | Inside Container  | Drush must be run inside a full running stack inside the container.                                                                                                                 |
| MySQL Import/Export     | Depends           | CLI operations using `mysql` or `mysqldump` can be run inside container. A client connection from outside the container (MySQL Workbench) is also supported on port 33060.          |
| Environment/Stack Edits | Outside Container | The environment/stack is immutable, even in local dev environments. When altering any environment files (e.g. Dockerfile, docker-compose.yml) the containers must be rebuilt.       |

Note also that individual commands can be executed inside the container from an environment outside the container using the [docker exec](https://docs.docker.com/engine/reference/commandline/exec/) (e.g. `docker exec -i base drush status`). This can be useful for running quick drush and composer commands without leaving a local git-bash or VSCode window.

### Debugging

Debugging with VSCode and XDebug is supported. A project-specific `.vscode/launch.json` is even included to tell VSCode how to listen to XDebug inside the container and setup appropriate mappings for breakpoints. However, there are some one-time setup steps needed to get a local development environment working with Xdebug:

1. Install the [PHP Debug Adapter](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug) for VSCode.
2. Install the XDebug Helper ([Firefox](https://addons.mozilla.org/en-US/firefox/addon/xdebug-helper-for-firefox/) or [Chrome](https://chrome.google.com/webstore/detail/xdebug-helper/eadndfjplgieldjbigjakmdgkmoaaaoc)).

For performance reasons XDebug is also not installed and enabled inside the container by default, but a script is available to easily activate it. Note that this step needs to be repeated anytime the container is rebuilt. From inside the container (Docker Desktop command line) simply run:

```bash
~/install-xdebug
```

Then to start a debugging session:

1. Go to the "Run and Debug" panel in VSCode and select the "Listen for XDebug (base)" option (where "base" is the project folder) and click the Play button. This will start VSCode's debugging processes and tell it to start listening for connections from XDebug.
2. Go to your browser address bar, click the XDebug Helper icon, and select "Debug". This will tell your browser to set a cookie that XDebug will respond to.
3. Set some breakpoints and load a page that triggers the code you want to test.