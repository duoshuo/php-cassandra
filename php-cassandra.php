<?php
/* This file can be used instead of install with composer.
 * Just include "require 'PATH/php-cassandra/php-cassandra.php';" to your code (where PATH is path to php-cassandra folder).
 */

require 'src/Exception.php';

require 'src/Type/Base.php';
require 'src/Type/Varchar.php';
require 'src/Type/Ascii.php';
require 'src/Type/Bigint.php';
require 'src/Type/Blob.php';
require 'src/Type/Boolean.php';
require 'src/Type/CollectionList.php';
require 'src/Type/CollectionMap.php';
require 'src/Type/CollectionSet.php';
require 'src/Type/Counter.php';
require 'src/Type/Custom.php';
require 'src/Type/Decimal.php';
require 'src/Type/Double.php';
require 'src/Type/Exception.php';
require 'src/Type/PhpFloat.php';
require 'src/Type/Inet.php';
require 'src/Type/PhpInt.php';
require 'src/Type/Timestamp.php';
require 'src/Type/Uuid.php';
require 'src/Type/Timeuuid.php';
require 'src/Type/Tuple.php';
require 'src/Type/UDT.php';
require 'src/Type/Varint.php';

require 'src/Protocol/Frame.php';

require 'src/Connection/SocketException.php';
require 'src/Connection/Socket.php';
require 'src/Connection/StreamException.php';
require 'src/Connection/Stream.php';

require 'src/Request/Request.php';
require 'src/Request/AuthResponse.php';
require 'src/Request/Batch.php';
require 'src/Request/Execute.php';
require 'src/Request/Options.php';
require 'src/Request/Prepare.php';
require 'src/Request/Query.php';
require 'src/Request/Register.php';
require 'src/Request/Startup.php';

require 'src/Response/StreamReader.php';
require 'src/Response/Response.php';
require 'src/Response/Authenticate.php';
require 'src/Response/AuthSuccess.php';
require 'src/Response/Error.php';
require 'src/Response/Event.php';
require 'src/Response/Exception.php';
require 'src/Response/Ready.php';
require 'src/Response/Result.php';
require 'src/Response/Supported.php';

require 'src/Connection.php';
require 'src/Statement.php';
