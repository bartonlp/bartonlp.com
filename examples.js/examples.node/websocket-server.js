#!/usr/bin/env node
// This server runs on port 8080. The html files should be run from the
// browser as 'www.bartonlp.com/examples.node/...html'

// NOTE: For the myphotochannel app this can be run via upstart see the
// websocket.conf here and in /etc/init. We could make a systemd config
// file also but have not yet.

// https://github.com/Worlize/WebSocket-Node/wiki/Documentation

/* Replace Pusher.com
 * Instead of using Pusher this program accepts four events:
 * 1) register. Send by programs that want to receive messages. Either
 * the siteId is send or ALL. Programs that want to monitor messages to
 * ALL sites set siteId to ALL.
 * 2) fastcall. This is triggered by actions from the Cpanel. When:
 * a) a photo is approved
 * b) the following tables are modified: appinfo, categories, segments,
 * sites, or items.
 * 3) startup. Send when a program starts, like cpanel or slideshow.
 * 4) shutdown. Send to ALL by this program when a connection
 * terminates.
 *
 * There still needs to be logic added for bingo and lotto.
 */

/* Websocket Status Codes
   code	 Name	                Description
   0-999      	 	            Reserved and not used.
   1000	CLOSE_NORMAL	        Normal closure; the connection successfully completed whatever purpose
                              for which it was created.
   1001	CLOSE_GOING_AWAY	    The endpoint is going away, either because of a server failure or
                              because the browser is navigating away from the page that opened the
                              connection.
   1002	CLOSE_PROTOCOL_ERROR	The endpoint is terminating the connection due to a protocol error.
   1003	CLOSE_UNSUPPORTED	    The connection is being terminated because the endpoint received data
                              of a type it cannot accept
                              (for example, a text-only endpoint received binary data).
   1004	 	                    Reserved. A meaning might be defined in the future.
   1005	CLOSE_NO_STATUS	      Reserved.  Indicates that no status code was provided even though one
                              was expected.
   1006	CLOSE_ABNORMAL	      Reserved. Used to indicate that a connection was closed abnormally
                              (that is, with no close frame being sent)
                              when a status code is expected.
   1007	 	                    The endpoint is terminating the connection because a message was
                              received that contained inconsistent data (e.g., non-UTF-8 data within
                              a text message).
   1008	 	                    The endpoint is terminating the connection because it received a message
                              that violates its policy. This is a generic status code, used when codes
                              1003 and 1009 are not suitable.
   1009	CLOSE_TOO_LARGE	      The endpoint is terminating the connection because a data frame was
                              received that is too large.
   1010	 	                    The client is terminating the connection because it expected the server
                              to negotiate one or more extension, but the server didn't.
   1011	 	                    The server is terminating the connection because it encountered an
                              unexpected condition that prevented it from fulfilling the request.
   1012-1014	 	              Reserved for future use by the WebSocket standard.
   1015	 	                    Reserved. Indicates that the connection was closed due to a failure to
                              perform a TLS handshake (e.g., the server certificate can't be verified).
   1016-1999	 	              Reserved for future use by the WebSocket standard.
   2000-2999	 	              Reserved for use by WebSocket extensions.
   3000-3999	 	              Available for use by libraries and frameworks. May not be used by
                              applications.
   4000-4999	 	              Available for use by applications.
*/

var WebSocketServer = require('websocket').server; // websocket: npm install websocket

var dateformat = require('dateformat'); // dateformat: npm install dateformat

var fs = require('fs'); // internal to nodejs
var http = require('http'); // internal to nodejs

// Logging function for websocket.log
// Make sure that websocket.log is owned by barton and group is
// www-data and mod is 664

var now = new Date; // get start date/time

// Make sure we can read and write to the log file

fs.appendFileSync("/var/www/bartonlp/examples.js/examples.node/websocket.log", "\n" + now + " Websocket Startup\n");
// 1000=uid barton, 33=uid/gid www-data
fs.chownSync("/var/www/bartonlp/examples.js/examples.node/websocket.log", 1000, 33);
// chmod u=rw g=rw o=r (user rw, group rw, other r)
fs.chmodSync("/var/www/bartonlp/examples.js/examples.node/websocket.log", 0664);

// Log information to websocket.log, If mode === true then don't add the date

function logit(msg, mode) {
  if(mode !== true) {
    var now = dateformat();
    msg = now + ', ' + msg;
  }
  console.log(msg);
}

// Create an http server. If someone actually tries to connect send a
// 403 back.

var server = http.createServer(function(request, response) {
  logit('Received request for ' + request.url + " from: " +
        request.connection.remoteAddress);
  response.writeHead(403); // Forbiden
  response.end("<h1>403 Forbiden</h1>"+
               "<h2>Go Away</h2>"+
               "<p>This port is used for WebSockets. "+
               "You should run one of the 'websocket-...html' files from "+
               "http://www.bartonlp.com/examples.node/websocket-...html.</p>");
});

// Start listening on port 8080

server.listen(8080, function() {
  logit('Websocket-server is listening on port 8080');
});

// Create the websocket server

wsServer = new WebSocketServer({
  httpServer: server,
  // You should not use autoAcceptConnections for production
  // applications, as it defeats all standard cross-origin protection
  // facilities built into the protocol and the browser.  You should
  // *always* verify the connection's origin and decide whether or not
  // to accept it.
  autoAcceptConnections: false
});

// Check the origin of the connection

function originIsAllowed(origin, r) {
  //console.log("r:", r, origin)
  if(r.BLP == '8653') return true;
  return false;
}

// Array to hold the connections and an array index.
// This array of connections grows and shrinks as connects are
// initiated and disconnected. Therefore inx increases and decreses as
// the array is resized at the onclose event where we re-index the
// array.

var c = [], inx = 0;

// When we receive a request

wsServer.on('request', function(request) {
  if(!originIsAllowed(request.origin, request.resourceURL.query)) {
    // Make sure we only accept requests from an allowed origin
    request.reject();
    logit('Connection from origin ' + request.origin + ' rejected.\n');
    return;
  }

  // Catch the NO protocal exception

  try {
    var connection = request.accept('slideshow', request.origin);
    //console.log("slideshow");
  } catch(e) {
    logit("request.accept Error: "+ e);
    return false;
  }
  
  connection.inx = inx;
  c[inx++] = connection; // Add to connections array
  
  logit('Connection accepted. inx: ' + connection.inx);

  // Bind to message event

  // MESSAGE
  
  connection.on('message', function(message) {
    // We got a message

    if(message.type === 'utf8') {
      logit('Received Message: inx: '+ connection.inx + ", " + message.utf8Data);
      // message is a json object
      // jmsg keys: event, siteId, ...
      // event: register, fastcall, startup, startup-update, shutdown
      
      var jmsg = JSON.parse(message.utf8Data);
      
      if(typeof connection.siteId == 'undefined') {
        connection.siteId = jmsg.siteId;
        c[connection.inx].siteId = jmsg.siteId;
      }
      if(typeof connection.prog == 'undefined') {
        connection.prog = jmsg.prog;
        c[connection.inx].prog = jmsg.prog;
      }

      switch(jmsg.event) {
        case 'hello':
          connection.sendUTF("Hello World: " + jmsg.prog); // send to the client that messaged me
          // Send this info to programs that have registered as ALL

          for(var i=0; i < c.length; ++i) {
            if(connection.inx == c[i].inx) {
              continue; // skip this connection
            }

            // If the siteId matchs ALL then send the message to the site

            if(c[i].siteId == "ALL") {
              //console.log("c[i]: %d, connection: %d", c[i].inx, connection.inx);

              logit(jmsg.event + "::ALL, inx: "+
                    c[i].inx +"\n\tsiteId: "+jmsg.siteId+
                    ", inx: "+connection.inx+", prog: "+jmsg.prog);

              c[i].sendUTF(JSON.stringify({event: jmsg.event,
                                          siteId: jmsg.siteId,
                                          prog: jmsg.prog,
                                          inx: connection.inx}));
            }
          }
          break;
        case 'register':
          // Tell the sender that he is registered

          connection.sendUTF(JSON.stringify({event: 'register',
                                            message: 'Register OK',
                                            siteId: jmsg.siteId,
                                            prog: connection.prog,
                                            inx: connection.inx}));
          
          break;
        case 'startup': // on startup
        case 'startup-update': // after slideshow.ajax::getItem:photo
          var id = jmsg.siteId; // id of starting program

          // Send this info to programs that have registered as ALL

          for(var i=0; i < c.length; ++i) {
            if(connection.inx == c[i].inx) {
              continue; // skip this connection
            }

            // If the siteId matchs ALL then send the message to the site

            if(c[i].siteId == "ALL") {
              //console.log("c[i]: %d, connection: %d", c[i].inx, connection.inx);
              
              logit(jmsg.event + "::ALL, inx: "+
                    c[i].inx +"\n\tsiteId: "+jmsg.siteId+
                    ", inx: "+connection.inx+", prog: "+jmsg.prog);

              c[i].sendUTF(JSON.stringify({event: jmsg.event,
                                          siteId: jmsg.siteId,
                                          prog: jmsg.prog,
                                          inx: connection.inx}));
            }
          }
          
          break;
        case 'shutdown':
          // This is handled by the onclose event which send shutdown
          // to programs that registered as ALL
          break;
        case 'newinx': // playbingo
        case 'fastcall':
          // event, siteId
          // fastcall gets sent to all connections with this siteId
          // BLP 2014-10-10 -- *** we should probably not send to ourself
          // if(connection == c[i]) skip
          
          var id = jmsg.siteId;

          var ret = {};
          for(e in jmsg) {
            ret[e] = jmsg[e];
          }
          ret['inx'] = connection.inx;

          jsonret = JSON.stringify(ret);
          
          // Loop through the connections in c

          for(var i=0; i < c.length; ++i) {
            logit(jmsg.event + " inx: "+c[i].inx+
                  "\n\tsiteId: "+ c[i].siteId + ", prog: "+c[i].prog);
            
            if(connection.inx == c[i].inx) {
              logit("Skip Self: "+connection.inx);
              continue; // skip this connection
            }
            
            // If the siteId's match then send the message to the site
            // If ALL then send all messages to this connection.

            if(c[i].siteId == "ALL" || c[i].siteId == id) {
              // tell the site to do a fastCall()
              if(c[i].siteId == "ALL") {
                logit(jmsg.event+"::ALL, inx: "+
                      c[i].inx+"\n\tsiteId: "+jmsg.siteId+
                      ", prog: "+jmsg.prog+
                      ", inx: "+connection.inx);
                
                c[i].sendUTF(JSON.stringify(ret));
              } else {
                logit(jmsg.event+"::Send to siteId: "+c[i].siteId+
                      ", prog: "+jmsg.prog+
                      ", inx: "+c[i].inx);
                
                logit(jsonret);
                
                c[i].sendUTF(jsonret);
              }
            }
          }
          break;
        default:
          logit("Event Error, inx: "+connection.inx+", "+ jmsg.event);
          break;
      }
    } else {
      logit('ERROE: Message NOT utf8, inx:' + connection.inx);
      //connection.sendBytes(message.binaryData);
    }

    logit("Waiting");
  });

  // CLOSE
  
  connection.on('close', function(reasonCode, description) {
    logit('Disconnected: Code: '+reasonCode+
          ', Desc: '+description+
          '\n\tPeer: ' + connection.remoteAddress +
          ', siteId: '+ connection.siteId +
          ', prog: ' + connection.prog+
          '\n\tinx: ' + connection.inx);

    // anyone registered ALL

    for(var i=0; i < c.length; ++i) {
      // If the siteId's match then send the message to the site

      if(c[i].siteId == "ALL") {
        if(connection.inx == c[i].inx) {
          continue; // skip this connection
        }

        logit("shutdown::ALL, inx: "+
              c[i].inx+"\n\tsiteId: "+connection.siteId+
              ", prog: "+connection.prog+
              ", inx: "+connection.inx);

        c[i].sendUTF(JSON.stringify({event: 'shutdown',
                                    siteId: connection.siteId,
                                    prog: connection.prog,
                                    inx: connection.inx}));
      }
    }

    // Remove from c array
    // 0, 1x, 2, 3
    // 0, R, 1, 2

    for(var i=0; i < inx; ++i) {
      logit("\tArray index: "+i+", siteId: "+ c[i].siteId+
            ", prog: "+c[i].prog+
            ", inx: "+c[i].inx, true);
      
      if(c[i].inx == connection.inx) {
        logit("\tRemoved: inx: "+ c[i].inx+
              ", siteId:"+c[i].siteid+
              ", prog: "+c[i].prog, true);
        
        c.splice(i--,1);
        --inx;
      } else {
        // re-index the remaining items
        c[i].inx = i;
      }
    }

//    for(var i=0; i < c.length; ++i) {
//      logit("c: "+i+", inx: "+c[i].inx+", prog: "+c[i].prog);
//    }
    
    logit("\n", true);
  });
});

