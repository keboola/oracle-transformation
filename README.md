# Oracle transformation

[![Build Status](https://travis-ci.com/keboola/snowflake-transformation.svg?branch=master)](https://travis-ci.com/keboola/snowflake-transformation)

Application which runs KBC transformations

## Options

- `parameters`
    - `db` array (required): credentials for database
        - `host` string (required)
        - `port` string (required) 
        - `user` string (required) 
        - `#password` string (required) 
        - `database`string (required)
        - `schema` string (optional) 
    - `blocks` array (required): list of blocks
        - `name` string (required): name of the block
        - `codes` array (required): list of codes
            - `name` string (required): name of the code
            - `script` array (required): list of sql queries

## Example configuration

```json
{
  "parameters": {
    "db": {
      "host": "oracle_host",
      "port": "oracle_port",
      "user": "oracle_user",
      "#password": "oracle_password",
      "database": "oracle_database",
      "schema": "oracle_schema"
    },
    "blocks": [
      {
        "name": "first block",
        "codes": [
          {
            "name": "first code",
            "script": [
              "CREATE TABLE \"testout\" AS SELECT * FROM \"SIMPLE\"",
              "UPDATE \"testout\" SET age = id"
            ]
          }
        ]
      }
    ]
  },
  "storage": {
    "input": {
      "tables": [
        {
          "destination": "simple",
          "source": "in.c-main.simple",
          "column_types": {
            "id": {
              "type": "VARCHAR",
              "source": "id",
              "destination": "id",
              "length": "255",
              "convert_empty_values_to_null": true
            },
            "name": {
              "type": "VARCHAR",
              "source": "name",
              "destination": "name",
              "length": "255",
              "convert_empty_values_to_null": true
            },
            "glasses": {
              "type": "VARCHAR",
              "source": "glasses",
              "destination": "glasses",
              "length": "255",
              "convert_empty_values_to_null": true
            },
            "age": {
              "type": "INTEGER",
              "source": "age",
              "destination": "age",
              "length": "",
              "convert_empty_values_to_null": true
            }
          }
        }
      ]
    },
    "output": {
      "tables": [
        {
          "destination": "out.c-test.test-out",
          "source": "testout"
        }
      ]
    }
  }
}
```


## Development
 
Clone this repository with following command:

```
git clone https://github.com/keboola/oracle-transformation
cd oracle-transformation
```

Create `.env` file with following contents:
```
KBC_TOKEN=
KBC_RUNID=
KBC_URL=
ORACLE_DB_HOST=
ORACLE_DB_PORT=
ORACLE_DB_USER=
ORACLE_DB_PASSWORD=
ORACLE_DB_DATABASE=
ORACLE_DB_SCHEMA=
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
```

Init docker image and install dependencies:
```
docker-compose build
docker-compose run --rm dev composer install --no-scripts
```


Run the test suite using this command:

```
docker-compose run --rm dev composer tests
```