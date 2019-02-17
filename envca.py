#!/usr/bin/python
import urllib.request, urllib.parse, urllib.error, urllib.request, urllib.error, urllib.parse, http.client
import sys, getopt
import re
import json
from urllib.request import Request
from urllib.error import HTTPError, URLError
from datetime import datetime, timedelta, date, time
import xml.etree.ElementTree as ET
import config as cfg

DEBUG = False
url_air = 'http://weather.gc.ca/airquality/pages/multiple_stations/abaq-002_e.html'
url_current = 'http://weather.gc.ca/rss/city/ab-52_e.xml'
url_alert = 'http://weather.gc.ca/rss/warning/ab-52_e.xml'
url_precip = 'https://calgary.weatherstats.ca/data/precipitation-daily.js?key=wd01&browser_zone=Mountain%20Daylight%20Time'
url_solar = 'https://calgary.weatherstats.ca/data/solar_radiation-hourly.js?key=wd01&browser_zone=Mountain%20Daylight%20Time'
url_cloud = 'https://calgary.weatherstats.ca/data/cloud_cover_8-hourly.js?key=wd01&browser_zone=Mountain%20Daylight%20Time'
url_influxdb = cfg.echelon_url
items=dict()
payload=[]

opts, args = getopt.getopt(sys.argv[1:], "d:", ["debug="])
for o, v in opts:
    if o in ("-d", "--debug"):
        DEBUG = True
if url_air == None:
    raise ValueError("check config. bad url_air")

    
#### CURRENT CONDITIONS ####

request = urllib.request.Request(url_current)
request.add_header('User-agent',cfg.user_agent)
try:
    response = urllib.request.urlopen(request)
    data = response.read()
    
    root = ET.fromstring(data)
    entries = root.findall('{http://www.w3.org/2005/Atom}entry')
    
    start = -1 ## account for possible alerts to take up first spot.
    for x in range (0,len(entries)-1):
        ## Forecast
        try:
            today = entries[x][0].text
            if today.split(' ')[0] == 'Current': 
                # find first instance of today
                start = x
                
                ##Capture for today first.
                items=dict()
                timestamp = entries[start][2].text
                summary = entries[start][5].text
                            
                ## capture the temp
                regex = re.compile(r'Temperature:<\/b> ((?:\d|\.|-){1,5})')
                m = regex.search(summary)
                if m:
                    items.update({'Temperature':float(m.group(1).replace(",",""))})
                    
                ## capture the pressure
                regex = re.compile(r'Pressure \/ Tendency:<\/b> ((?:\d|\.|-){1,5}) kPa')
                m = regex.search(summary)
                if m:
                    items.update({'BaroPressure':float(m.group(1).replace(",",""))})
                    
                ## capture the visibility
                regex = re.compile(r'Visibility:<\/b> ((?:\d|\.|-){1,5})')
                m = regex.search(summary)
                if m:
                    items.update({'Visibility':float(m.group(1).replace(",",""))})
                    
                ## capture the humidity
                regex = re.compile(r'Humidity:<\/b> ((?:\d|\.|-){1,5})')
                m = regex.search(summary)
                if m:
                    items.update({'Humidity':float(m.group(1).replace(",",""))})
                    
                ## capture the dew point
                regex = re.compile(r'Dewpoint:<\/b> ((?:\d|\.|-){1,5})')
                m = regex.search(summary)
                if m:
                    items.update({'Dewpoint':float(m.group(1).replace(",",""))})
                    
                ## capture the Wind
                regex = re.compile(r'Wind:<\/b> ((?:[NESW]){1,3}) ((?:\d|\.|-){1,5}) km\/h gust ((?:\d|\.|-){1,5}) km\/h')
                m = regex.search(summary)
                if m:
                    WindDirections = {'N':1,
                        'NNE':1.5,
                        'NE':2,
                        'ENE':2.5,
                        'E':3,
                        'ESE':3.5,
                        'SE':4,
                        'SSE':4.5,
                        'S':5,
                        'SSW':5.5,
                        'SW':6,
                        'WSW':6.5,
                        'W':7,
                        'WNW':7.5,
                        'NW':8,
                        'NNW':8.5,
                        'N':9}
                    items.update({'WindDirection':float(WindDirections[m.group(1).replace(",","")])})
                    items.update({'WindSpeedLow':float(m.group(2).replace(",",""))})
                    items.update({'WindSpeedHigh':float(m.group(3).replace(",",""))})
                    items.update({'WindSpeedAvg':((float(m.group(3).replace(",","")) + float(m.group(2).replace(",",""))) / 2)})
                    
                payload.append({'tags':{'host':'envca'},'fields':items,'timestamp':timestamp})
                
            ## now capture for every other day.
            if start >= 0 and x-start >= 0:
                day = 0
                
                for n in range (0,6): 
                    if (datetime.now()+timedelta(days=n)).strftime("%A") == today.split(' ')[0].split(':')[0]:
                        day = n
                    elif n > 5:
                        continue
                    
                items=dict()
                today = entries[x][0].text
                timestamp = (datetime.strptime(entries[x][2].text,"%Y-%m-%dT%H:%M:%SZ") + timedelta(days=day)).strftime("%Y-%m-%dT%H:%M:%SZ")
                summary = entries[x][5].text

                ###low
                regex = re.compile(r'Low ((?:\d|-){1,5})')
                m = regex.search(today)
                if m:
                    items.update({'forecast_low_plus'+str(day):float(m.group(1).replace(",",""))})
                    
                ### high
                regex = re.compile(r'High ((?:\d|-){1,5})')
                m = regex.search(today)
                if m:
                    items.update({'forecast_high_plus'+str(day):float(m.group(1).replace(",",""))})
                    
                if day <= 1:
                    ## uv forecast for tomorrow only
                    regex = re.compile(r'UV index ((?:\d){1,2})')
                    m = regex.search(summary)
                    if m:
                        items.update({'forecast_uv':float(m.group(1).replace(",",""))})
                    
                payload.append({'tags':{'host':'envca'},'fields':items,'timestamp':timestamp})
        except IndexError:
            if DEBUG: 
                print('out of index')
            # no forecast
            
except HTTPError as e:
    if DEBUG:
        print('ERROR CODE:', e.code)
        #print 'ERROR DATA:', e.read()
except URLError as e:
    if DEBUG:
        print('ERROR REASON:', e.reason)


#### AIR QUALITY ####

request = urllib.request.Request(url_air)
request.add_header('User-agent',cfg.user_agent)
try:
    response = urllib.request.urlopen(request)
    data = response.read()
    items=dict()

    ## capture the date.
    regex = re.compile(r'Calculated at:<\/a[^>]*[^\d]*>(.*)<\/div><')
    m = regex.search(data.decode('utf-8'))
    if m:
        update_time = datetime.strptime(m.group(1), '%I:%M %p %Z %A %d %B %Y')
        # 3:00 PM MST Wednesday 19 December 2018

    ## capture the timezone
    regex = re.compile(r' (M(?:.)T)')
    m = regex.search(m.group(1))
    if m:
        if m.group(1) == 'MDT':
            timezone = "-06:00"
        else :
            timezone = "-07:00"
        
    ## capture the nw
    regex = re.compile(r'Calgary Northwest<\/a[^>]*[^\d]*>((?:\d|\.|-){1,4})<\/b>')
    m = regex.search(data)
    if m:
        items.update({'air_qual_nw':float(m.group(1).replace(",",""))})
        
    ## capture the central
    regex = re.compile(r'Calgary Central<\/a[^>]*[^\d]*>((?:\d|\.|-){1,4})<\/b>')
    m = regex.search(data)
    if m:
        items.update({'air_qual_central':float(m.group(1).replace(",",""))})
        
    ## capture the se
    regex = re.compile(r'Calgary Southeast<\/a[^>]*[^\d]*>((?:\d|\.|-){1,4})<\/b>')
    m = regex.search(data)
    if m:
        items.update({'air_qual_se':float(m.group(1).replace(",",""))})
        
    ## capture the overall
    regex = re.compile(r'Calgary<\/a[^>]*[^\d]*>((?:\d|\.|-){1,4})<\/b>')
    m = regex.search(data)
    if m:
        items.update({'air_qual_overall':float(m.group(1).replace(",",""))})
        
except HTTPError as e:
    if DEBUG:
        print('ERROR CODE:', e.code)
        #print 'ERROR DATA:', e.read()
#    sys.exit()
except URLError as e:
    if DEBUG:
        print('ERROR REASON:', e.reason)
#    sys.exit()
payload.append({'tags':{'host':'envca'},'fields':items,'timestamp':update_time.isoformat()+timezone})

#### ALERTS ####

request = urllib.request.Request(url_alert)
request.add_header('User-agent',cfg.user_agent)
try:
    response = urllib.request.urlopen(request)
    data = response.read()
    
    root = ET.fromstring(data)
    entries = root.findall('{http://www.w3.org/2005/Atom}entry')
    for entry in entries:
        items=dict()
        title = entry[0].text
        timestamp = entry[2].text
        summary = entry[5].text
                            
        ## capture the alerts
        regex = re.compile(r'(.*) (WARNING|WATCH|STATEMENT) (.*),')
        m = regex.search(title)
        if m:
            items.update({'alert_title':title,
            'alert_tag': m.group(2),
            'alert_status': m.group(3),
            'alert_summary': summary})
                    
        payload.append({'tags':{'host':'envca'},'fields':items,'timestamp':timestamp})
 
except HTTPError as e:
    if DEBUG:
        print('ERROR CODE:', e.code)
        #print 'ERROR DATA:', e.read()
except URLError as e:
    if DEBUG:
        print('ERROR REASON:', e.reason)
        
        
#### PRECIPITATION ####

request = urllib.request.Request(url_precip)
request.add_header('User-agent',cfg.user_agent)
try:
    response = urllib.request.urlopen(request)
    data = response.read()
    
    ## capture the data.
    needle = re.compile(r'{"v":new Date\( ((?:\d){4}), ((?:\d){1,2}), ((?:\d){1,2}) \)},{"v":((?:[\d]|\.){1,5})}')
    ## set the timezone
    timezone = "-06:00"
    for (year,month,day,value) in re.findall(needle, data): 
        items=dict()
        items.update({'Precipitation':float(value)})
        # Super weird... the month is a 0 based array, the day and year are not.
        d = date(int(year), int(month)+1, int(day))
        t = time(0,0)
        dt = datetime.combine(d,t)
        payload.append({'tags':{'host':'wstats'},'fields':items,'timestamp':dt.isoformat()+timezone})

                
except HTTPError as e:
    if DEBUG:
        print('ERROR CODE:', e.code)
        #print 'ERROR DATA:', e.read()
#    sys.exit()
except URLError as e:
    if DEBUG:
        print('ERROR REASON:', e.reason)

#### SOLAR RADIATION ####

request = urllib.request.Request(url_solar)
request.add_header('User-agent',cfg.user_agent)
try:
    response = urllib.request.urlopen(request)
    data = response.read()
    
    ## capture the data.
    needle = re.compile(r'{"v":new Date\( ((?:\d){4}), ((?:\d){1,2}), ((?:\d){1,2}), ((?:\d){1,2}), 0, 0 \)},{"v":((?:[\d]|\.){1,7})}')
    ## set the timezone
    timezone = "-06:00"
    for (year,month,day,hour,value) in re.findall(needle, data): 
        items=dict()
        items.update({'solar_radiation':float(value)})
        # Super weird... the month is a 0 based array, the day and year are not.
        d = date(int(year), int(month)+1, int(day))
        t = time(int(hour),0)
        dt = datetime.combine(d,t)
        payload.append({'tags':{'host':'wstats'},'fields':items,'timestamp':dt.isoformat()+timezone})

                
except HTTPError as e:
    if DEBUG:
        print('ERROR CODE:', e.code)
        #print 'ERROR DATA:', e.read()
except URLError as e:
    if DEBUG:
        print('ERROR REASON:', e.reason)

#### CLOUD COVER ####

request = urllib.request.Request(url_cloud)
request.add_header('User-agent',cfg.user_agent)
try:
    response = urllib.request.urlopen(request)
    data = response.read()
    
    ## capture the data.
    needle = re.compile(r'{"v":new Date\( ((?:\d){4}), ((?:\d){1,2}), ((?:\d){1,2}), ((?:\d){1,2}), 0, 0 \)},{"v":((?:[\d]|\.){1,3})}')
    ## set the timezone
    timezone = "-06:00"
    for (year,month,day,hour,value) in re.findall(needle, data): 
        items=dict()
        items.update({'cloud_cover':float(value)})
        # Super weird... the month is a 0 based array, the day and year are not.
        d = date(int(year), int(month)+1, int(day))
        t = time(int(hour),0)
        dt = datetime.combine(d,t)
        payload.append({'tags':{'host':'wstats'},'fields':items,'timestamp':dt.isoformat()+timezone})

                
except HTTPError as e:
    if DEBUG:
        print('ERROR CODE:', e.code)
        #print 'ERROR DATA:', e.read()
#    sys.exit()
except URLError as e:
    if DEBUG:
        print('ERROR REASON:', e.reason)
#    sys.exit()



#### POST IT TO INFLUXDB HERE ####
if DEBUG:
    print('DEBUG:', payload)

# make a string with the request type in it:
method = "POST"
# build a request
if DEBUG: 
    request = urllib.request.Request(url_influxdb+"&test")
else:
    request = urllib.request.Request(url_influxdb)
# add any other information you want
request.add_header('User-agent',cfg.user_agent)
request.add_header('API-Key',cfg.echelon_api_key)
request.add_header("Content-Type",'application/json')
# overload the get method function with a small anonymous function...
request.get_method = lambda: method
request.add_data(json.dumps(payload))

print(json.dumps(payload))

if DEBUG: 
    print("DEBUG (method): ", method)

# try it; don't forget to catch the result
try:
    connection = urllib.request.urlopen(request)
except urllib.error.HTTPError as e:
    connection = e

# check. Substitute with appropriate HTTP code.
if DEBUG:
    print('DEBUG (code):',connection.code)
if connection.code == 200:
    data = connection.read()
    if DEBUG:
        print(data)
