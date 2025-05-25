Okay, here is the instruction for the LLM model integrated into an IDE (like Cursor), along with the content of the `plan.md` file.

**Instruction for the LLM (IDE-integrated):**

"Hello! Your task is to help me refactor my PHP project `TradingBot` for publication on GitHub. Below you will find a detailed action plan. This plan is located in the `plan.md` file in the project root.

**Your workflow:**

1.  **Open the `plan.md` file.**
2.  **Carefully read the current task** with the checkbox `[ ]`.
3.  **Execute the task**, using your capabilities to access the file system (creating, editing, moving files, executing commands in the terminal, if needed).
4.  **After successfully completing the task, OPEN `plan.md` AGAIN and EDIT it, replacing `[ ]` with `[x]` for the completed task.** This is very important for tracking progress. Save the `plan.md` file after making the change.
5.  **Proceed to the next task `[ ]`.**
6.  If a task is too large or complex, let me know, and we can break it down into smaller steps.
7.  If anything is unclear or you need clarification, please ask.
8.  Be careful when modifying files, especially code and configurations. If you are unsure, it is better to ask.
9.  After completing several logically related tasks, I may ask you to make a git commit.

**The main goal is the structure and presentability of the project, not its full functionality.**

Please start with the first uncompleted task in `plan.md`."

---

Now the content of the `plan.md` file, which you will create in the project root:

```markdown
# Plan for Refactoring and Structuring the "TradingBot" Project for GitHub (for IDE LLM)

Goal: Prepare the "TradingBot" project for publication on GitHub as an example of a well-structured project with readable code. Functionality and perfect operation are not the priority.

**IMPORTANT for LLM:** After completing each item, mark it as done by changing `[ ]` to `[x]` in this file, and save the file.

## I. General Project Preparation

-   [ ] **README.md in the project root:**
    -   Open the `doc/Readme.md` file. Copy its content.
    -   Create (if it doesn't exist) or open the `README.md` file in the project root directory.
    -   Paste the copied content from `doc/Readme.md` into the root `README.md`.
    -   Add a brief project description at the beginning of `README.md`: "TradingBot - is a PHP project that simulates the operation of a trading bot. Key technologies: PHP 8.1, Docker, Composer, Apache/Nginx."
    -   Ensure that `README.md` has sections: "Installation", "Running (Locally and Docker)", "Project Structure". If they are missing, add them with appropriate headings (e.g., `## Installation`).
    -   Save the `README.md` file.
-   [ ] **Update `.gitignore`:**
    -   Open the `.gitignore` file in the project root directory.
    -   Ensure the following lines are present and not commented out (add if missing):
        ```
        /vendor/
        /node_modules/
        /data/logs/*
        !/data/logs/.gitkeep
        /data/pids/*
        !/data/pids/.gitkeep
        /data/storage/*
        !/data/storage/.gitkeep
        *.lock
        composer.phar
        .env
        .env.*
        !.env.example
        # IDE specific
        .idea/
        .vscode/
        *.sublime-project
        *.sublime-workspace
        # OS specific
        .DS_Store
        Thumbs.db
        # Temp files
        *.tmp
        *.swp
        *.swo
        # Build artifacts
        dist/
        build/
        ```
    -   Save the `.gitignore` file.
-   [ ] **Adding the `LICENSE` file:**
    -   Create the `LICENSE` file in the project root directory.
    -   Add the MIT license text to it (find the standard MIT license text online and paste it).
    -   Save the `LICENSE` file.
-   [ ] **Review `CONTRIBUTING.md`:**
    -   Open the `CONTRIBUTING.md` file. Read it. The file already exists and looks good. No changes are needed if its content is current.

## II. Project and File Structure

-   [ ] **Aligning the location of API-specific Docker and Apache files:**
    -   Rename the `api/` directory (the one in the project root containing `Dockerfile` and `.htaccess`) to `web_api_config/`.
    -   Update paths in the `docker-compose.yml` file (root) if it refers to the old `api/` directory for build context or volumes of the `api` service. Change `context: ./api` to `context: ./web_api_config` and `volumes: - ./.htaccess:/var/www/html/.htaccess` to `volumes: - ./web_api_config/.htaccess:/var/www/html/.htaccess` (if such lines exist for the service using `api/Dockerfile`). Also check other `docker-compose-*.yml` files.
-   [ ] **Moving Swagger UI:**
    -   Create the `public/swagger/` directory.
    -   Move the contents of the `public/docs/` directory (`index.html`, `swagger.json`, `swagger.php` files) to the newly created `public/swagger/` directory.
    -   Delete the empty `public/docs/` directory.
    -   Update the path to `swagger.json` in the `public/swagger/index.html` file from `url: "/swagger.json"` to `url: "/swagger/swagger.json"`.
    -   Update the path to `swagger.json` in the `public/swagger/swagger.php` file from `readfile(__DIR__ . '/swagger.json');` to `readfile(__DIR__ . '/swagger.json');` (if `swagger.php` is next to `swagger.json`) or `readfile(__DIR__ . '/../swagger/swagger.json');` (if `swagger.php` remained in a different location - clarify this).
    -   Update the path to `index.html` in the `public/swagger/swagger.php` file from `readfile(__DIR__ . '/index.html');` to `readfile(__DIR__ . '/index.html');` (if `swagger.php` is next to `index.html`).
    -   In the `index.php` file (root), change the paths for Swagger:
        -   `readfile(__DIR__ . '/public/docs/index.html');` to `readfile(__DIR__ . '/public/swagger/index.html');`
        -   `readfile(__DIR__ . '/public/docs/swagger.json');` to `readfile(__DIR__ . '/public/swagger/swagger.json');`
        -   `strpos($path, '/docs/') === 0` to `strpos($path, '/swagger/') === 0`
        -   `$filePath = __DIR__ . '/public' . $path;` to `$filePath = __DIR__ . '/public' . $path;` (this line likely doesn't need changes if `path` already contains `/swagger/`)
        -   `header('Location: /swagger-ui');` leave as is if Nginx/Apache configurations are set up for this path.
    -   Save all modified files.
-   [ ] **Cleaning up the root directory (moving files):**
    -   Create the `doc/project_files/` directory if it doesn't exist.
    -   Move the `router.php` file from the root directory to `doc/project_files/router.php`.
    -   Move the `tradeServerApiExampls.json` file from the root directory to `doc/api_examples/tradeServerApiExampls.json` (create `doc/api_examples/`, if it doesn't exist).
    -   Move the `routesMap.md` file from the root directory to `doc/project_files/routesMap.md`.
-   [ ] **Deleting unnecessary files from the root:**
    -   Delete the `translatePlan.md` file from the root directory.

## III. Documentation

-   [ ] **Updating `README.md` with links to documentation:**
    -   Open the root `README.md`.
    -   Add a `## Documentation` section.
    -   In this section, add links to key documents in `doc/`, for example:
        ```markdown
        - [Project Architecture](doc/Project_Architecture.md) (or `doc/Code_Architecture.md` - choose the more relevant one)
        - [Bot Configuration](doc/Bot_config.md)
        - [User Guide](doc/Confluence/UserGuide.md)
        - [Swagger API](/public/swagger/index.html) (or a link to the actual Swagger UI endpoint)
        ```
    -   Save the `README.md` file.
-   [ ] **Comments in `src/core/TradingBot.php` code:**
    -   Open the `src/core/TradingBot.php` file.
    -   Add a PHPDoc block for the `TradingBot` class.
    -   Add PHPDoc blocks for the main public methods: `__construct`, `updateConfig`, `initialize`, `runSingleCycle`, `clearAllOrders`, `placeLimitOrder`, `cancelOrder`, `placeMarketOrder`. Describe the method's purpose, its parameters, and what it returns.
    -   Remove commented-out code if it's not needed (e.g., `// $bidAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.19, 8, '.', '');`).
    -   Save the file.
-   [ ] **Translating `utils/README.md`:**
    -   Find the `utils/README.md` file.
    -   Translate its content to English.
    -   Replace the existing Ukrainian content with the translated English content.
    -   Save the file. (If this is too complex or beyond your current capabilities, skip this item and inform me).

## IV. Code Quality (PHP)

-   [ ] **Composer Autoload PSR-4:**
    -   Open the `composer.json` file.
    -   In the `autoload.psr-4` section, change `"App\\": "./"` to `"App\\": "src/"`.
    -   Execute the command `composer dump-autoload -o` in the terminal in the project root.
    -   Now check if all classes in the `src/` directory use the correct namespaces. For example, the `Logger` class in `src/core/Logger.php` should have the namespace `namespace App\Core;`. The `BotManager` class in `src/api/BotManager.php` should have `namespace App\Api;`. Correct namespaces where necessary to match the directory structure inside `src/` relative to the base namespace `App`.
    -   In the `src/helpers/LogManager.php` file, change `namespace App\helpers;` to `namespace App\Helpers;` (with a capital letter). Similarly for `LogRotator.php`. Update the usage of these namespaces in other files (e.g., in `src/core/Logger.php` it should be `use App\Helpers\LogManager;`).
    -   Save all modified files.
-   [ ] **Removing `classmap` from `composer.json`:**
    -   Open the `composer.json` file.
    -   Completely remove the `classmap` and `exclude-from-classmap` sections from `autoload`, as PSR-4 should cover everything in `src/`.
    -   Save `composer.json`.
    -   Execute the command `composer dump-autoload -o` in the terminal.
-   [ ] **Typing in `src/core/ApiClient.php`:**
    -   Open the `src/core/ApiClient.php` file.
    -   For the `get` method: add the return type `: string`.
    -   For the `post` method: add the return type `: string`.
    -   Save the file.

## V. Configuration and Scripts

-   [ ] **Comments for `scripts/clean_and_run_local.sh`:**
    -   Open the `scripts/clean_and_run_local.sh` file.
    -   Add comments explaining the main blocks of commands (e.g., `# Stop existing processes`, `# Clean up PID files`, `# Start backend`, `# Start frontend`, `# Start bots`).
    -   Save the file.
-   [ ] **Update `scripts/rebuild-all.sh` for new Dockerfile paths:**
    -   Open the `scripts/rebuild-all.sh` file.
    -   Ensure that `docker-compose build` commands (if they specify specific services to build) and `docker-compose-*.yml` files use updated paths to `Dockerfile` and configurations if they changed as a result of renaming `api/` to `web_api_config/` and moving files. For example, if in `docker-compose-dev.yml` for the `backend-dev` service it was `build: ./api`, and now `api/Dockerfile` has moved to `web_api_config/Dockerfile` (if so), then it needs to be updated. *The current structure seems to use `backend/Dockerfile` and `frontend/Dockerfile`, so this step may not require changes, but check carefully.*
    -   Save the file.

## VI. Docker and Environment

-   [ ] **Update `docker-compose.yml` (root) for `web_api_config`:**
    -   Open the `docker-compose.yml` file (root).
    -   Find the `api` service.
    -   Change `context: .` to `context: ./web_api_config` (if `api/Dockerfile` is now `web_api_config/Dockerfile`). If the `Dockerfile` for this service is located in `web_api_config/Dockerfile`, the context path should be `./web_api_config`. If the `Dockerfile` is named `Dockerfile` and is in `web_api_config`, then `context: ./web_api_config` and `dockerfile: Dockerfile` (or just `context: ./web_api_config`).
    -   Change `volumes: - ./.htaccess:/var/www/html/.htaccess` to `volumes: - ./web_api_config/.htaccess:/var/www/html/.htaccess`.
    -   Save the file.
-   [ ] **Check ports in `docker-compose-demo.yml` and `docker-compose-dev.yml`:**
    -   Open `docker-compose-dev.yml`. Ensure that the ports for `backend-dev` (should be 5501) and `frontend-dev` (should be 5502) match the documentation.
    -   Open `docker-compose-demo.yml`. Ensure that the ports for `backend-demo` (should be 6501) and `frontend-demo` (should be 6502) match the documentation.
    -   Save the files if there were changes.

## VII. Frontend (`frontend/`)

-   [ ] **Update `frontend/js/config.js`:**
    -   Open the `frontend/js/config.js` file.
    -   Change `apiUrl: 'http://localhost:8080/api'` to `apiUrl: '/api'` (relative path, as Nginx will proxy).
    -   Change `swaggerUrl: 'http://localhost:8080/swagger-ui'` to `swaggerUrl: '/swagger-ui'` (relative path).
    -   Remove the part with `window.addEventListener('DOMContentLoaded', ...)` for dynamic URL updates from headers, as we are now using relative paths that are correctly handled by Nginx.
    -   Save the file.
-   [ ] **Update Nginx configurations for Swagger:**
    -   Open `frontend/nginx-dev.conf`.
    -   Ensure that `location /swagger-ui` and `location /swagger.json` proxy requests to the backend, which now serves Swagger UI from `public/swagger/index.html` and `public/swagger/swagger.json`. Paths in `proxy_pass` for Swagger may need adjustment if the backend serves them at different internal URLs after moving.
        For example, if `index.php` (backend) now handles the `/swagger-ui` request and serves `public/swagger/index.html`, then `proxy_pass http://backend-dev:8080/swagger-ui;` might be correct. The same applies to `swagger.json`.
    -   Repeat for `frontend/nginx-demo.conf`.
    -   Save the files.

## VIII. Final Touches

-   [ ] **Verify the project via Docker:**
    -   Run `scripts/rebuild-all.sh`.
    -   Check the accessibility of the frontend and Swagger UI for dev and demo environments at the ports specified in the script (e.g., `http://localhost:5502` for dev frontend, `http://localhost:5501/swagger-ui` for dev Swagger).
-   [ ] **Final review of `plan.md`:**
    -   Ensure all completed tasks are marked with `[x]`.