# StorX-api
A REST API for [StorX](https://github.com/aaviator42/StorX).

Current library version: `3.5` | `2020-10-11`  
Current DB file version: `3.0`

License: `AGPLv3`

## About

StorX API allows you to interact with your StorX DB files over a network. 

Simply place your DB files, `StorX.php` and `receiver.php` on a server, and now the DB files can be operated on using the API exposed by `receiver.php`.

> Use [StorX-Remote](https://github.com/aaviator42/StorX-Remote) to easily interact with this API over the network from PHP scripts. 
> If you're using StorX-Remote, then you can safely ignore everything about endpoints and requests in this doc, the remote library will handle everything for you.


## Configuration

Place `receiver.php` and `StorX.php` in the same folder, or modify the `require` statement at the top of the receiver to point to wherever `StorX.php` is.

In `StorX.php`, ensure that `THROW_EXCEPTIONS` is set to `FALSE`.

There are three constants that you can modify at the top of `receiver.php`:
 * `DATA_DIR`: Points to the root directory where DB files are stored. Note that the API *does not disallow* requests to DB files outside of this folder.
 * `USE_AUTH`: If this is `true`, then API requests will fail if they don't include the correct password in the payload. 
 * `PASSWORD_HASH`: A hash generated using PHP's `password_hash()` with `PASSWORD_BCRYPT`.  
 See [this](https://github.com/aaviator42/hashgen) script to make simplify this process. 
 Default password is `1234`. Obviously, do not use this.
 

## Stuff you should know
 * Unlike regular `StorX`, a separate request does not need to be made to write changes made to DB files to disk. All changes are written to disk after each key write/modify/delete request.

## Requests

> **Note:** You can simplify interacting with this API from other PHP scripts using [StorX-Remote](https://github.com/aaviator42/StorX-Remote). Then you don't have to bother with any of the following.


Refer below for a list of endpoints, and what HTTP methods should be used to interact with them. In your request body, include a JSON-encoded associative array with the necessary data. 

If the receiver is using authentication, then include the password as a key in the payload.

For example, let's say the API receiver password is `1234` and you want to write a key `username` with value `aaviator42` to the DB file `testDB.dat`. You would send a `PUT` request to `<url>/receiver.php/writeKey` with the following request body:

```json
{
  "password": "1234",
  "filename": "testDB.dat",
  "keyName": "username",
  "keyValue": "aaviator42"
}
```

## Output
The API returns an JSON-encoded associative array, with the following keys:
 * `error`: 0 if all okay, 1 if error occurs while the API processes the request.
 * `errorMessage`: Contains an error message if an error takes place.
 * `returnCode`: Contains the value returned by the function mapped to the endpoint

Addionally, `/readKey`'s output also contains this key:
 * `keyValue`: `base64_encode(serialize($keyValue))`


<br>

`returnCode` has a few special values:

value  | description
-------|---------
`-666` | Invalid request
`-777` | Authentication failed
`-2`   | Error opening file
`-3`   | Error writing changes to disk

HTTP status codes returned by the API:
value  | description
-------|---------
`400` | Invalid request
`401` | Authentication failed
`200` | All OK / mapped function executed successfully
`201` | Resource created successfully
`409` | Error executing requested changes



## Endpoints

method | endpoint | description | input values 
-------|----------|-------------|--------------
GET    | /checkFile | Maps to `\StorX\checkFile()` | `filename`
GET    | /readKey | Maps to `\StorX\Sx::readKey()` | `filename`, `keyName`
GET    | /checkKey | Maps to `\StorX\Sx::checkKey()` | `filename`, `keyName`
PUT    | /createFile | Maps to `\StorX\createFile()` | `filename`
PUT    | /writeKey | Maps to `\StorX\Sx::writeKey()` | `filename`, `keyName`, `keyValue`
PUT    | /modifyKey | Maps to `\StorX\Sx::modifyKey()` | `filename`, `keyName`, `keyValue`
DELETE | /deleteFile | Maps to `\StorX\deleteFile()` | `filename`
DELETE | /deleteKey | Maps to `\StorX\Sx::deleteKey()` | `filename`, `keyName`

There's a special `GET` endpoint `ping` that doesn't require authentication. It is used to ensure that the remote and receiver are of matching versions. The input is expected to have a single key `version` with a string value containing the version of the remote. The output contains two keys, `version` that contains the receiver's version, and `pong` that contains `OK` if the versions match, and `ERR` if they don't. 




 ----
 
 Documentation updated `2020-10-11`

