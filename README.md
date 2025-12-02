# 📁 Joomla 5 Content Folder Lister Plugin

> **Plugin Name:** Content - Folder Lister (PlgContentFolderlister)
>
> **Project Description:** A powerful content plugin for Joomla 5 that allows you to embed dynamic, navigable directory listings (local filesystem, FTP, or SFTP) directly into your articles.

---

## ✨ Features

This plugin transforms a simple tag in your Joomla article into a fully functional, styleable file browser.

* **Multi-Protocol Support:** List directories from the local server's filesystem, or connect to remote servers via **FTP** and **SFTP**.
* **Front-End Navigation:** Users can navigate through subdirectories (folders) directly within the article view.
* **Dynamic Sorting:** Includes a modern front-end dropdown to sort files and folders by **Name (A-Z/Z-A)** and **Date (Newest/Oldest)**.
* **Joomla 5 Ready:** Built for compatibility and performance on the latest Joomla major version.
* **Customizable Path:** Define the base directory (local or remote URL) using a simple content tag.

---

## 🛠️ Requirements

* Joomla! 5.x
* PHP 8.2+
* For **SFTP** support, the PHP SSH2 extension must be installed on your server.
* For **FTP** support, the standard PHP FTP extension must be enabled.

---

## 📦 Installation

Since this is an open-source project, you will need to package the plugin files correctly for standard Joomla installation.

1.  **Download:** Get the latest release package (or zip the required files).
2.  **Joomla Administrator:** Navigate to **System** -> **Install** -> **Extensions**.
3.  **Upload:** Upload the ZIP file.
4.  **Enable:** Go to **System** -> **Manage** -> **Plugins**. Search for **"Content - Folder Lister"** (or similar) and **enable** it.

---

## 📝 Usage

To embed a folder listing into any Joomla article, use the following content tag:

### Local File Listing

To list a directory on your local server, simply provide the absolute path.

> **Note:** The plugin will only list files/folders accessible by the web server user.

{folderlister path="/var/www/html/your/docs/folder"}

### Remote File Listing (FTP / SFTP)

You can use a remote URL path. The plugin will attempt to connect and list the directory contents.

> **Security Warning:** Including sensitive credentials (username/password) directly in the content tag is **NOT recommended** for publicly accessible articles. This should primarily be used for internal, secure environments.

**FTP Example:**

{folderlister path="ftp://user:password@ftp.example.com/remote/folder"}

**SFTP Example:**

{folderlister path="sftp://user:password@sftp.example.com:22/remote/folder"}

---

## ⚙️ Development Details

### Plugin Structure

The core functionality resides in the `PlgContentFolderlister` class.

* `onContentPrepare()`: Main entry point, searches for the `{folderlister...}` tag.
* `renderFileList()`: Determines if the path is local (`file://` assumed) or remote (`sftp://` / `ftp://`) and delegates rendering.
* `renderLocalList()`: Handles standard local filesystem scanning (`scandir`).
* `renderRemoteList()`: Handles remote connections using `ssh2_sftp` or standard `ftp` functions.

### Content Tag Regex

The plugin uses the following regex to find the content tag:

```php
$regex = '/{folderlister\s+path="([^"]+)"}/i';

```

## Contributing

Contributions are welcome! If you have any suggestions, bug reports, or want to implement new features (like WebDAV support), please open an issue or a pull request.
