# Apache Hive DB writer

[![Build Status](https://travis-ci.com/keboola/db-writer-hive.svg?branch=master)](https://travis-ci.com/keboola/db-writer-hive)

[KBC](https://www.keboola.com/product/) Docker app for writing data to [Apache Hive](https://hive.apache.org/) database.

See [Database Writers](https://help.keboola.com/components/writers/database/) for more documentation.

# Usage

## Configuration

The configuration `config.json` contains following properties in `parameters` key: 

- `db` - object (required): Connection settings
    - `host` - string (required): IP address or hostname of Apache Hive DB server
    - `port` - integer (required): Server port (default port is `10000`)
    - `user` - string (required): User with correct access rights
    - `#password` - string (required): Password for given `user`
    - `database` - string (required): Database to connect to
    - `ssh` - object (optional): Settings for SSH tunnel
        - `enabled` - bool (required):  Enables SSH tunnel
        - `sshHost` - string (required): IP address or hostname of SSH server
        - `sshPort` - integer (optional): SSH server port (default port is `22`)
        - `localPort` - integer (required): SSH tunnel local port in Docker container (default `33006`)
        - `user` - string (optional): SSH user (default same as `db.user`)
        - `compression`  - bool (optional): Enables SSH tunnel compression (default `false`)
        - `keys` - object (optional): SSH keys
            - `public` - string (optional): Public SSH key
            - `#private` - string (optional): Private SSH key
- `tableId` - string (required): Name of the input CSV file, eg. `sales`
- `dbName` - string (required): Name of the target table, eg. `sales`
- `incremental` - bool (optional): Enables incremental write, existing table will not be deleted (default `false`)
- `primaryKey` - array (optional): 
    - Primary key (simple or composite)
    - If used with incremental write, existing data with the same PK will be updated
    - [PK is not currently supported by Hive DB](https://www.quora.com/Is-there-a-primary-key-and-foreign-key-concept-in-Apache-Hive-and-Spark-SQL), but this setting is used with incremental write 
- `items` - array (required): Columns whose will be written
    - `name` - string (required): Column name in input CSV file
    - `dbName` - string (required): Column name in target database
    - `type` - string (required): Column type
    - `size` - scalar (optional): Column size, required for `char`, `decimal`, `varchar` 
    - `nullable` - bool (optional): Ignored, currently not supported by Hive DB (default `false`)
    - `default` - bool (optional): Ignored, currently not supported by Hive DB (default `false`)

## Examples

Full write, if the table exists, it will be deleted first:
```json
{
  "parameters": {
    "db": {
      "host": "hive-server",
      "port": 10000,
      "database": "default",
      "user": "admin",
      "#password": "******"
    },
    "tableId": "users",
    "dbName": "users",
    "items": [
      {
        "name": "id",
        "dbName": "id",
        "type": "int"
      },
      {
        "name": "name",
        "dbName": "name",
        "type": "varchar",
        "size": 255
      }
    ]
  }
}
```

Incremental write, data will be added:
```json
{
  "parameters": {
    "db": { "host": "..." },
    "tableId": "users",
    "dbName": "users",
    "incremental": true,
    "items": [
      {
        "name": "id",
        "dbName": "id",
        "type": "int"
      },
      {
        "name": "name",
        "dbName": "name",
        "type": "varchar",
        "size": 255
      }
    ]
  }
}
```

Incremental write with PK, new data will be added, existing data will be updated:
```json
{
  "parameters": {
    "db": { "host": "..." },
    "tableId": "users",
    "dbName": "users",
    "incremental": true,
    "primaryKey": ["id"],
    "items": [
      {
        "name": "id",
        "dbName": "id",
        "type": "int"
      },
      {
        "name": "name",
        "dbName": "name",
        "type": "varchar",
        "size": 255
      }
    ]
  }
}
```

## Development
 
Clone this repository and init the workspace with following command:

```
git clone https://github.com/keboola/db-writer-hive
cd db-writer-hive
docker-compose build
docker-compose run --rm wait
docker-compose run --rm dev composer install --no-scripts
```

Run the test suite using this command:

```
docker-compose run --rm dev composer tests
```
 
# Integration

For information about deployment and integration with KBC, please refer to the [deployment section of developers documentation](https://developers.keboola.com/extend/component/deployment/) 
