# Mini Inventory System (Kubernetes 3-tier Example)

This repository contains a simple PHP/MySQL inventory management application designed
to be deployed on the same 3-tier Kubernetes architecture as the original
`3tier_todo_app`.  The project keeps the same number of Pods (MySQL, phpMyAdmin
and the PHP application) and uses the same ports and secrets.  Only the
application code and database schema have been changed to implement a more
realistic CRUD use-case with multiple tables.

## Features

This project expands the original todo example into a **professional‑looking web
application** that demonstrates key DevOps concepts while remaining easy to
deploy on a three‑tier Kubernetes cluster.  Notable features include:

1. **User authentication** – A `users` table stores hashed passwords using
   SHA‑256.  A login page (`login.php`) validates credentials and starts
   sessions.  The application checks for a logged‑in user before granting
   access to any inventory pages.  A logout button terminates the session.
2. **CRUD operations on four entities** – Products, suppliers, purchases and
   orders can be created, read, updated and deleted.  Forms use prepared
   statements to prevent SQL injection.  Records are displayed in Bootstrap
   tables for improved readability.
3. **File uploads** – Each product can have an image (JPEG/PNG) uploaded and
   stored in the `uploads/` directory.  Purchases and orders support an
   optional attachment (PDF or image) representing invoices or order forms.
   Uploaded file paths are persisted in the database and displayed as
   downloadable links in the UI.
4. **Data visualisation** – A separate Reports page (`reports.php`) aggregates
   data from the database and renders interactive charts using Chart.js.
   Examples include bar charts of current inventory levels, doughnut charts
   for order status distribution and line charts comparing purchases vs
    orders over the last seven days.  Additional time‑series analytics such
    as purchases per day (last 30 days) and purchases vs orders per month
    (last 12 months) demonstrate how to track trends across days, weeks and
    months, providing a more realistic view of warehouse activity.
5. **Modern UI** – The site uses Bootstrap 5 for styling and responsiveness.
   A navigation bar shows the current section and the logged‑in username,
   with quick access to the Reports page and logout functionality.

Each feature is implemented in plain PHP without external frameworks, making
the code easy to follow.  The application is designed to be secure and
configurable via environment variables (see below).

## Database

The database schema is provided in the `inventory.sql` file.  It defines
five tables (`products`, `suppliers`, `purchases`, `orders` and `users`) and
inserts a few starter rows.  The `products` table includes an `image_path`
column for storing relative paths to uploaded product images.  The
`purchases` and `orders` tables include an `attachment_path` column for
optional PDF or image attachments.  The `users` table stores the
username, SHA‑256 hashed password and role for each account; the default
admin account is created with username `admin` and password `adminpass`.

You can import this file manually using phpMyAdmin or have MySQL load it
automatically by mounting it into `/docker-entrypoint-initdb.d/` when
creating the MySQL pod.  Kubernetes will execute any `.sql` files in this
directory on first launch【871876182943783†L274-L277】.

## Running in Kubernetes

This project is designed to run on the same three‑tier cluster described in
the tutorial.  The following high‑level steps illustrate how to get up and
running with the enhanced features.  Replace resource names and labels as
appropriate for your cluster.

1. **Build the Docker image** using the included `Dockerfile`:

   ```bash
   # from the root of this repository
   docker build -t your-registry/inventory-app:latest .
   docker push your-registry/inventory-app:latest
   ```

   The `Dockerfile` creates an `uploads` directory inside the container
   where product images and attachments will be stored.  Ensure that the
   underlying persistent volume (if used) has write permissions for this
   directory.

2. **Create a Kubernetes Secret** for the MySQL root password (reuse
   `rootpassword` to match the provided PHP code):

   ```bash
   kubectl create secret generic mysql-pass --from-literal=password=rootpassword
   ```

3. **Create a ConfigMap** supplying the database name and user.  Keep the
   key names the same as in the original tutorial so that the deployment
   manifests remain unchanged.  The password is pulled from the Secret
   created in Step 2:

   ```yaml
   apiVersion: v1
   kind: ConfigMap
   metadata:
     name: mysql-config
   data:
     MYSQL_DATABASE: sqldb
     MYSQL_USER: root
   ```

4. **Deploy the MySQL Pod and Service** using the same YAML used in
   `3tier_todo_app`.  Mount the `inventory.sql` file into the pod at
   `/docker-entrypoint-initdb.d/` so that MySQL loads the schema on
   startup.  This will create the five tables and the default admin user.

   ```yaml
   volumeMounts:
     - name: init-db
       mountPath: /docker-entrypoint-initdb.d
   volumes:
     - name: init-db
       configMap:
         name: inventory-sql
         items:
           - key: inventory.sql
             path: inventory.sql
   ```

5. **Deploy phpMyAdmin** exactly as in the tutorial.  It connects to the
   MySQL service via the `CLUSTER_IP` and exposes a `NodePort` for
   browser access.

6. **Deploy the Inventory Application Deployment and Service**.  Use the
   same manifest structure as the todo app, but update the image reference
   to `your-registry/inventory-app:latest`.  Ensure environment variables
   for `DB_HOST`, `DB_NAME`, `DB_USER` and `DB_PASSWORD` match the
   ConfigMap and Secret created earlier.  Mount a persistent volume (PVC)
   at `/var/www/html/uploads` if you want uploaded files to survive pod
   restarts.  Alternatively, rely on the container’s filesystem for
   transient storage.

7. **Access the application** via the NodePort assigned to the PHP
   deployment.  Navigate to `/login.php` first to authenticate using the
   default admin credentials (`admin` / `adminpass`).  Once logged in
   you can add products, suppliers, orders and purchases, upload files,
   and view charts from the Reports menu.  Use phpMyAdmin to inspect
   or modify the underlying tables if needed.

For more detailed guidance on creating Secrets, ConfigMaps and Services, you
can refer back to the original deployment instructions or Kubernetes
documentation.
