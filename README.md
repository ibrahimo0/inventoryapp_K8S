# Mini Inventory System (Kubernetes 3-tier Example)

This repository contains a simple PHP/MySQL inventory management application designed
to be deployed on the same 3-tier Kubernetes architecture as the original
`3tier_todo_app`.  The project keeps the same number of Pods (MySQL, phpMyAdmin
and the PHP application) and uses the same ports and secrets.  Only the
application code and database schema have been changed to implement a more
realistic CRUD use-case with multiple tables.

## Features

The application lets you manage four core entities often found in small
warehouse systems:

1. **Products** – Track the name, description, price, quantity on hand and the
   supplier of each item.
2. **Suppliers** – Maintain a list of vendors with contact details.
3. **Purchases** – Record inbound stock receipts from suppliers including the
   product, quantity and date.
4. **Orders** – Record outbound customer orders with the product, quantity,
   date and status.

All entities support the ability to create, read, update and delete records
through a web interface.  Each section is accessible via a simple navigation
menu at the top of the page.

## Database

The database schema is provided in the `inventory.sql` file.  It defines
the four tables and inserts a few starter rows.  You can import this file
manually using phpMyAdmin or have MySQL load it automatically by mounting
it as a volume in your Kubernetes `mysql` pod (similar to the original
example that loads `simple_todo.sql`).

## Running in Kubernetes

This project is designed to run on the same three-tier cluster described in
the tutorial.  The following high-level steps illustrate how to get up and
running.  Replace resource names and labels as appropriate for your cluster.

1. **Build the Docker image** using the included `Dockerfile`:

   ```bash
   # from the root of this repository
   docker build -t your-registry/inventory-app:latest .
   docker push your-registry/inventory-app:latest
   ```

2. **Create a Kubernetes Secret** for the MySQL root password (reuse
   `rootpassword` to match the provided PHP code):

   ```bash
   kubectl create secret generic mysql-pass --from-literal=password=rootpassword
   ```

3. **Create a ConfigMap** supplying the database name, user and
   password.  Keep the key names the same as the tutorial so that the
   deployment manifests remain unchanged:

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
   startup:

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

6. **Deploy the Inventory Application Pod and Service**.  Use the same
   deployment manifest as the todo app, but update the image reference to
   point to `your-registry/inventory-app:latest` from Step 1.  Ensure
   environment variables for `DB_HOST`, `DB_NAME`, `DB_USER` and
   `DB_PASSWORD` match the ConfigMap and Secret created earlier.

7. **Access the application** via the NodePort assigned to the PHP pod.
   Use phpMyAdmin to view or modify the underlying tables if needed.

For more detailed guidance on creating Secrets, ConfigMaps and Services, you
can refer back to the original deployment instructions or Kubernetes
documentation.
