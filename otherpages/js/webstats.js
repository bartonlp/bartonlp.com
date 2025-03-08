/*
 * webstats.js for http://www.bartonphillips.net/webstats.php. Uses
 * webstats-ajax.php for AJAX calls.
 */

'use strict';

const DEBUG = false; // BLP 2023-10-17 - if true we do the debug_performanceObserver function.

const flags = {all: false, webmaster: false, bots: false, ip6: true};
const ajaxurl = 'https://bartonlp.com/otherpages/webstats-ajax.php'; // URL for all ajax calls.

function removeAll() {
  $("#Human").remove();
  $("#Overflow").remove();
  $("#FindBot").remove();
  $("#outer").hide();
}

function debug_performanceObserver() {
  try {
    // Create the performance observer.
    const po = new PerformanceObserver((list) => {
      //console.log("list: ", list);

      for(const entry of list.getEntries()) {
      // Logs all server timing data for this response
      //console.log("entry: ", entry);
        let date = entry.serverTiming[0];
        let time = entry.serverTiming[1];
        console.log('Server Timing: date='+ date.description + ', time=' + time.duration / 1e6);
      }
    });
    // Start listening for navigation entries to be dispatched.

    po.observe({type: 'navigation', buffered: true});
  } catch (e) {
    // Do nothing if the browser doesn't support this API.
    console.log("ERROR: ", e);
  }
  try {
    const po1 = new PerformanceObserver(list => {
      for(const entry of list.getEntries()) {
        console.log("Name: " + entry.name + `
                    Type: `
                    + entry.entryType +
                    ", Start: " + entry.startTime +
                    ", Duration: " + entry.duration
                   );
      }
    });
    po1.observe({type: 'resource', buffered: true});
  } catch(e) {}
}

// For 'tracker'
// The .bots class is set in webstats-ajax.php.
// homeIp, thesite, myIp, robots and tracker are set in
// webstats.php in the inlineScript.

function hideIt(f) {
  switch(f) {
    case 'all':
      $(".all, .webmaster, .bots").hide();
      $(".normal").show();
      $("#webmaster").text("Show webmaster");
      $("#bots").text("Show bots");
      break;
    case 'webmaster': // default is don't show
      $(".webmaster").hide();
      break;
    case 'bots': // true means we are showing robots
      $('.bots').hide();
      break;
  }
  flags[f] = false;
  let msg = "Show ";
  $("#"+ f).text(msg + f);
  calcAv();
  return;
}   

function showIt(f) {
  switch(f) {
    case 'all':
        // bots and all can be together
      $(".all").show();
      $(".bots").hide();
      break;
    case 'webmaster':
      $(".webmaster").show();
      break;
    case 'bots':
      $(".bots").show();
      break;
  }
  flags[f] = true;
  let msg = "Hide ";
  $("#"+ f).text(msg + f);
  calcAv();
  return;
}

function calcAv() {
  // Calculate the average time spend using the NOT hidden elements

  let av = 0, cnt = 0;

  $("#tracker tbody :not(:hidden) td:nth-child(8)").each(function(i, v) { // 8 is difftime
    let t = $(this).text();
    if(t == '' || t == 0 || (typeof t == 'undefined')) {
      //console.log("t:", t);
      return true; // Continue, don't count blank
    }

    //console.log("t", t);

    let ar = t.match(/^(\d+):(\d{2}):(\d{2})$/);
    //console.log("ar: " + ar + "t:", t);
    t = parseInt(ar[1], 10) * 3600 + parseInt(ar[2],10) * 60 + parseInt(ar[3],10);

    if(t > 7200) {
      //console.log("Don't count: " + t);
      return true; // Continue if over two hours 
    }
    av += t;
    ++cnt;      
  });

  if(av) {
    av = av/cnt; // Average
  }
  let hours = Math.floor(av / (3600)); 

  let divisor_for_minutes = av % (3600);
  let minutes = Math.floor(divisor_for_minutes / 60);

  let divisor_for_seconds = divisor_for_minutes % 60;
  let seconds = Math.ceil(divisor_for_seconds);

  let tm = hours.pad()+":"+minutes.pad()+":"+seconds.pad();

  $("#average").html(tm);
}

Number.prototype.pad = function(size) {
  let s = String(this);
  while (s.length < (size || 2)) {s = "0" + s;}
  return s;
}

function getcountry() {
  let ip = $("#tracker tr td:first-child");
  let ar = new Array;

  ip.each(function() {
    let ipval = $(this).text();
    // remove dups. If ipval is not in the ar array add it once.
    if(!ar[ipval]) {
      ar[ipval] = 1; // true
    }
  });

  // we have made ipval true so we do not have duplicate

  ar = JSON.stringify(Object.keys(ar)); // get the key which is ipval and make a string like '["123.123.123.123", "..."', ...]'

  $.ajax(ajaxurl, {
    type: 'post',
    data: {list: ar},
    success: function(co) {
      let com = JSON.parse(co); // com is an array of countries by ip.
            
      ip.each(function(i) { // ip is the first td. We look at each td.
        ip = $(this).text();
        co = com[ip]; // co is the country
    
        // We make co-ip means country-ip.

        $(this).html("<span class='co-ip'>"+ip+"</span><br><div class='country'>"+co+"</div>");
      });
    },
    error: function(err) {
      console.log("ERROR:", err);
    }
  });
}

// Function to do all the stuff for tracker when it is Ajaxed in

function dotracker() {
  // To start Webmaster is hidden
  
  $("#logagent tbody td:nth-child(1)").each(function(i, v) {
    if(myIp.indexOf($(v).text()) !== -1) { // myIp was set in webstats.php in inlineScript
      if(homeIp === ($(v).text())) { // homeIp was set in webstats.php in inlineScript
        $(v).css({"color": "white", "background": "green"});
      } else {
        $(v).css({"color": "black", "background": "lightgreen"});
      }
    }
  });

  $("#logagent tbody td:nth-child(2)").each(function(i, v) {
    v = $(v);
    v.html((v.html().replaceAll(/</g, "&lt;")).replaceAll(/>/g, "&gt;"));
  });

  // Set class webmaster colors.
  
  $("#tracker tbody td:nth-child(1) span.co-ip").each(function(i, v) { // 1 is ip
    if(myIp.indexOf($(v).text()) !== -1) { // myIp was set in webstats.php in inlineScript
      if(homeIp === ($(v).text())) { // homeIp was set in webstats.php in inlineScript
        $(v).parent().css({ "color":"white", "background":"green"}).parent().addClass("webmaster").hide();
      } else {
        $(v).parent().css({"color":"black", "background":"lightgreen"}).parent().addClass("webmaster").hide();
      }
    }
  });

  // To start bots are hidden

  $(".bots td:nth-child(4)").css("color", "red").parent().hide();
  
  // What ever is left is normal

  $("#tracker tbody tr:not(:hidden)").addClass("normal");

  calcAv();
}

function ipaddress(e, self) {
  if(e.ctrlKey) {
    let msg;

    const id = $(self).closest("table").attr('id');
    console.log("id: " + id);

    if(id == 'tracker') {
      if(flags.ip) {
        flags.ip = false;
        $(".ip").removeClass("ip").hide();
        for(let f in flags) {
          if(flags[f] == true) {
            $("."+f).show();
          }
        }
        $(".normal").show();
        msg = "Show Only ID";
      } else {
        flags.ip = true;
        let ip = $(self).text();
        $("#tracker td:first-child").each(function(i, v) { // 1 is ip
          if($(v).text() == ip) {
            $(v).parent().addClass('ip');
          }
        });
        $("#tracker tbody tr").not(".ip").hide();
        msg = "Show All ID";
      }
      $("#ip").text(msg);
      return;
    }
  }

  let ip = $("span", self).text();
  let pos = $(self).position();
  let xpos = pos.left + $(self).width() + 10;
  let ypos = pos.top;
  let table = $(self).closest('table');

  console.log("IP: "+ip);

  if(e.type == "dblclick") {
    $.ajax(ajaxurl, {
      data: {page: 'curl', ip: ip},
      type: "post",
      success: function(data) {
        console.log(data);
        // For mobile devices there is NO ctrKey! so we don't
        // need to worry about position fixed not working!

        removeAll();

        table.append("<div id='FindBot' style='position: absolute;top: "+ypos+"px;left:"+xpos+"px;"+
                     "background-color: white; border: 5px solid black;padding: 10px'>"+
                     data+"</div>");
      },
      error: function(err) {
        console.log(err);
      }
    });
  } else { // No alt.
    let bottom = $(self).offset()['top'] + $(self).height();

    $.ajax(ajaxurl, {
      data: {page: 'findbot', ip: ip},
      type: "post",
      success: function(data) {
        removeAll();

        $("<div id='FindBot' style='position: fixed;top: 10px; "+
            "background-color: white; border: 5px solid black;padding: 10px'>"+
            data+"</div>").appendTo("body");

        if($("#FindBot").height() > window.innerHeight) {
          removeAll();

          $("<div id='FindBot' style='position: absolute;top: "+bottom+"px; "+
              "background-color: white; border: 5px solid black;padding: 10px'>"+
              data+"</div>").appendTo("body");
        }
      },
      error: function(err) {
        console.log(err);
      }
    });
  }
  e.stopPropagation();
}

function gettracker() {
  $.ajax(ajaxurl, {
    //url: directory+'/webstats-ajax.php',
    data: {page: 'gettracker', site: thesite, mask: mask, thedate: thedate}, // thesite is set in webstats via inlineScript
    type: 'post',
    success: function(data) {
      $("#trackerdiv").html(data);
      $("#tracker").tablesorter({theme: 'blue', headers: {6: {sorter: 'hex'}}});

      // Put a couple of buttons before the tracker table

      $("#tracker").parent().before("<div id='beforetracker'>Ctrl Click on the 'ip' items to <span id='ip'>Show Only ip</span>.<br>"+
                                    "Ctrl Click on the 'ip' to popup 'bots' information."+
                                    "Dbl Click on the 'ip' items to <span class='red'>Show http://ipinfo.io info</span><br>"+
                                    "Click on 'page' or 'agent' items to see the full field, or scroll the fields if needed.<br>"+
                                    "Ctrl Click on the 'page' items to <span id='page'>Show Only page</span>.<br>"+
                                    "Click on the 'js' items to see human readable info.<br>"+
                                    "Average stay time: <span id='average'></span> (times over two hours are discarded.)<br>"+
                                    "<button id='webmaster'>Show webmaster</button>"+
                                    "<button id='bots'>Show bots</button>"+
                                    "<button id='all'>Show All</button><br>"+
                                    "<button id='update'>Update Fields</button>"+
                                    "<button id='ip6only'>Hide IPV6</button>"+
                                    "</div>"
                                   );

      getcountry();
      dotracker();

      for(let f in flags) {
        if(flags[f]) { // if true
          switch(f) {
            case 'all':
              showIt('all');
              break;
            case 'webmaster':
              showIt('webmaster');
              break;
            case 'bots':
              showIt('bots');
              break;
          }
        }
      }

      // ShowHide all where js == 0

      $("#all").on("click", function(e) {
        if(flags.all) {
          hideIt('all');
        } else {
          // Show
          showIt('all');
          showIt('webmaster');
          showIt('bots');
        }
      });

      // ShwoHide Webmaster

      $("#webmaster").on("click", function(e) {
        if(flags.webmaster) {
          hideIt('webmaster');
        } else {
          // Show
          showIt('webmaster');
        }
      });

      // Ip6only

      $("#ip6only").on("click", function(e) {
        $("#tracker tbody tr td:nth-child(1)").each(function(i, v) { // 1 is id
          if($(this).text().match(/:/) != null ) {
            if(flags.ip6 === true) {
              $(this).parent().show();
            } else {
              $(this).parent().hide();
            }
          }
        });
        if(flags.ip6 === false) {
          $("#ip6only").text("Hide IPV6");
        } else {
          $("#ip6only").text("Show IPV6")
        }
        flags.ip6 = !flags.ip6;
      });

      // ShowHideBots

      $("#bots").on("click", function() {
        if(flags.bots) {
          // hide
          hideIt('bots');
        } else {
          // show
          showIt('bots');
        }
      });

      // Update the tracker info by getting the latest stuff.

      $("#update").on("click", function() {
        $("#beforetracker").remove();
        gettracker();
      });

      $("#tracker td:first-of-type").on("dblclick", function(e) {
        ipaddress(e, this);
      });
      
      $("#tracker td:first-of-type").on("click", function(e) { 
        ipaddress(e, this);
      });
      
    }, error: function(err) {
      console.log(err);
    }
  });
}

// After the DOM is complete.

jQuery(document).ready(function($) {
  if(DEBUG) debug_performanceObserver();

  $("body").on("click", function(e) {
    $("#Human").remove();
    $("#Overflow").remove();
    $("#FindBot").remove();
  });
  
  $("#robots2 td:nth-of-type(4)").each(function() {
    let botCode = $(this).text();
    $(this).text(robots[botCode]);
  });
  
  $("#logip, #logagent, #counter, #counter2, #robots, #robots2").tablesorter({
    theme: 'blue',
    sortList: [[0][1]]
  });
  
  // Add two special tablesorter functions: hex and strnum
  
  $.tablesorter.addParser({
    id: 'hex',
    is: function(s) {
          return false;
    },
    format: function(s) {
          return parseInt(s, 16);
    },
    type: 'numeric'
  });

  $.tablesorter.addParser({
    id: 'strnum',
    is: function(s) {
          return false;
        },
        format: function(s) {
          s = s.replace(/,/g, "");
          return parseInt(s, 10);
        },
        type: 'numeric'
  });

  // Set up analysis tables for tablesorter
  
  $("#os1, #os2, #browser1, #browser2")
  .tablesorter({ headers: { 1: {sorter: 'strnum'}, 2: {sorter: false}, 3: {sorter: false}}, sortList: [[1,1]]});

  // Set up robots for tablesorter
  
  $("#robots").tablesorter({headers: {3: {sorter: 'hex'}}});
 
  // Do this after the 'average' id is set.

  gettracker();

  // The robots tables doesn't need to be deligated.
  
  $("#robots").parent().before("Double Click the 'agent' items to <span class='botsshowhide'>Show Only</span> Agent<br>" +
                      "Click the 'bots' items for human readable info.");
  $("#robots2").parent().before("Double Click the 'agent' items to <span class='botsshowhide'>Show Only</span> Agent");

  // This is the agent fiels on both tables. If we double click it
  // toggles between showing all and showing only the one you double
  // clicked on.
  
  $("#robots td:nth-child(2), #robots2 td:nth-child(2)").on("dblclick", function() {
    let tr = $(this).closest('table').find('tr');
    let showhide = $(this).closest('table').prev().find('.botsshowhide');

    if(!this.flag) {
      let agent = $(this).text();
      tr.each(function(i, v) {
        if($("td:nth-of-type(2)", v).text() != agent) {
          $(v).hide();
        }
      });
      showhide.text("Show All");
    } else {
      tr.show();
      showhide.text("Show Only");
    }
    this.flag = !this.flag;
  });

  // Click on the ip address of any of the tables.
  // Look for ctrlKey and does show only ip.
  // Looks for altKey and does http://ipinfo.io via curl to get info on
  // ip.

  $("#logagent, #robots, #robots2").on("click", "td:first-child", function(e) {
    ipaddress(e, this);
  });

  // Popup a human version of 'isJavaScript' for tracker table and
  // 'robots' in bots table. Tracker table is column 9 and bots table
  // is column 4

  $("body").on("click", "#tracker td:nth-child(9), #robots td:nth-child(4)", function(e) {
    let js = parseInt($(this).text(), 16),
    h = '', ypos, xpos;
    let human;

    // Make it look like a hex. Then and it with 0x100 if it is true
    // then make js 0x1..
    
    //if('0x'+js & 0x100) js='0x'+js;
    
    let table = $(this).closest("table");
    let pos = $(this).position(); // get the top and left
    
    // The td is in a tr which in in a tbody, so table is three
    // prents up.

    if(table.attr("id") != 'tracker') {
      // Robots (bots table)
      
      human = robots; // robots was set in webstats.php in the inlineScript.
      
      xpos = pos.left + $(this).width() + 17; // add the one border and one padding (15px) plus a mig.
    } else {
      // Tracker table.
      
      human = tracker; // tracker was set in webstats.php in the inlineScript
      
      xpos = pos.left - 300; // Push this to the left so it will render full size
    }
    ypos = pos.top;

    console.log("human:", human);
    for(let [k, v] of Object.entries(human)) {
      h += (js & k) ? v + "<br>" : '';
    }

    removeAll();

    // Now append FindBot to the table.
    
    table.append("<div id='FindBot' style='position: absolute; top: "+ypos+"px; left: "+xpos+"px; "+
                 "background-color: white; border: 5px solid black; "+
                 "padding: 10px;'>"+h+"</div>");

    if(id == "tracker") {
      // For tracker recalculate the xpos based on the size of the
      // FindBot item.
      
      xpos = pos.left - ($("#FindBot").width() + 35); // we add the border and padding (30px) plus a mig.
      $("#FindBot").css("left", xpos + "px");
    }
    
    e.stopPropagation();
  });

  // Columns 2, 3 and 4 are page, finger and agent.
  // If just a click then show a popup with the full text.
  // If ctrl-click on agent with a 'http' prefix brings up the
  // website of the good-bot.
  // If ctrl-click on page (cellIndex 1) shows only that page (toggle).
  // There is no ctrl-click for finger.  
  
  $("body").on("click", "#tracker td:nth-of-type(2), #tracker td:nth-of-type(3), #tracker td:nth-of-type(4)", function(e) {
    removeAll();

    // Ctrl-click on agent shows a new tab with the http
    // information about the agent. 
    // Ctrl-click on page toggles the display from all pages to just
    // the page ctrl-clicked on and back.
    // Finger has no ctrl-click.
    
    if(e.ctrlKey) {
      if($(this)[0].cellIndex == 3) { // cellIndex 3 is td 4
        if($(this).css("color") == "rgb(255, 0, 0)") { // RED means it is a bot and may have information.
          const txt = $(this).text();
          const pat = /(http.?:\/\/.*?)\)/;
          const found = txt.match(pat);

          if(found) {
            window.open(found[1], "bot");
          }
        }
        e.stopPropagation();
      } else if($(this)[0].cellIndex == 2) { // cellIndex 2 is td 3
        let finger = ($(this).text()).split(' :')[0];
        let ip = $(this).closest('tr').find('.co-ip').text();
        let pos = $(this).position();
        let xpos = pos.left; // + $(this).width();
        let ypos = pos.top + 50;
        let table = $(this).closest("table");

        $.ajax(ajaxurl, {
          type: 'post',
          data: {page: 'fingerToGps', finger: finger, site: thesite, ip: ip},
          success: function(data) {
            console.log("data: ", data);
            let items = '';
            let cnt = 0;

            if(data != "NOT FOUND") {
              const ar = JSON.parse(data).sort();
              let last = '';

              for(let item in ar) {
                if(last == ar[item]) {
                  continue;
                }
                cnt++;
                last = ar[item];
                items += "<span class='item'>"+ar[item]+"</span><br>";
              }
            } else {
              items = data;
            }
            removeAll();

            table.append("<div id='FindBot' style='position: absolute;top: "+ypos+"px;left:"+xpos+"px;"+
                         "background-color: white; border: 5px solid black;padding: 10px'>"+
                         items+"</div>");
            if(cnt == 1) {
              $("#FindBot .item").trigger('click');
            }
          },
          error: function(err) {
            console.log("ERROR:", err);
          }
        });
        e.stopPropagation();
      } else if($(this)[0].cellIndex == 1) { // cellIndex 1 is td 2
        // Second field 'page' ctrl clicked

        let msg;

        if(flags.page) { // toggle flag
          flags.page = false;

          // flag is false so show all of the pages.
          
          $("#tracker tr").removeClass('page');

          for(let f in flags) {
            if(flags[f] == true) {
              $("."+f).show();
            }
          }
          $(".normal").show();
          msg = "Show Only Page";
        } else {
          // Show only the page. All information is just for this page.
          
          flags.page = true;
          let page = $(this).text();
          $("#tracker td:nth-child(2)").each(function(i, v) { // 2 is page
            if($(v).text() == page) {
              $(v).parent().addClass('page');
            }
          });
          $("#tracker tr").not(".page").hide();
          msg = "Show All Page";
        }
        $("#page").text(msg);
        e.stopPropagation();
      }
    } else {
      // This was NOT a ctrlKay. Just a normal 'click' show popup a
      // full display of the field.
      
      let ypos, xpos;
      let pos = $(this).position();
      xpos = pos.left - 200;
      ypos = pos.top;

      if($(this).text()) {
        // Display a pop up to show the full text of the field.
        
        $("#tracker").append("<div id='Overflow' style='position: absolute; top: "+ypos+"px; left: "+xpos+"px; "+
                             "background-color: white; border: 5px solid black; "+
                             "padding: 10px;'>"+$(this).text()+"</div>");
        e.stopPropagation()
      };
    };
  });

  // ipinfo. Get gps and display the google map.

  $("body").on("click", "#FindBot .location, #FindBot .item", function(e) {
    let t = $("#FindBot").position().top + $(this).height() + 10;

    removeAll();
    
    let gps = ($(this).text()).split(",");
    const pos = {
      lat: parseFloat(gps[0]),
      lng: parseFloat(gps[1])
    }

    let h, w, l;

    if(resized) {
      h = uiheight;
      w = uiwidth;
      t = uitop;
      l = uileft;
    } else {
      if(isMobile()) {
        h = "360px";
        w = "360px";
        l= "25%";
      } else {
        h = "500px";
        w = "500px";
        l = "50%";
      }
    }
    console.log("top="+t+", left="+l);

    if($("#tracker #outer").length == 0) {
      $("#tracker tbody").append($("#outer"));
    }
    
    marker.setOptions( {
      position: pos,
      map,
      visible: true
    });


    map.setOptions( {center: pos, zoom: 9, mapTypeId: google.maps.MapTypeId.HYBRID} );

    $("#outer").css({top: t, left: l, width: w, height: h}).show();

    e.stopPropagation();
  });
});
