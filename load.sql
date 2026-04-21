USE comet_commuter;

LOAD DATA LOCAL INFILE 'lines.dat'
INTO TABLE `Lines`
FIELDS TERMINATED BY '\t'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(line_id, line_name);

LOAD DATA LOCAL INFILE 'stations.dat'
INTO TABLE `Stations`
FIELDS TERMINATED BY '\t'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(station_id, station_name, latitude, longitude);

LOAD DATA LOCAL INFILE 'line_stations.dat'
INTO TABLE `Line_Stations`
FIELDS TERMINATED BY '\t'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(line_id, station_id);

LOAD DATA LOCAL INFILE 'alerts.dat'
INTO TABLE `Alerts`
FIELDS TERMINATED BY '\t'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(alert_id, station_id, radius, status, timestamp_created);