CREATE DATABASE IF NOT EXISTS comet_commuter;
USE comet_commuter;

CREATE TABLE `Lines` (
  line_id INT NOT NULL AUTO_INCREMENT,
  line_name VARCHAR(100) NOT NULL,
  PRIMARY KEY (line_id),
  UNIQUE KEY uq_line_name (line_name)
);

CREATE TABLE `Stations` (
  station_id INT NOT NULL AUTO_INCREMENT,
  station_name VARCHAR(100) NOT NULL,
  latitude DECIMAL(9,6) NOT NULL,
  longitude DECIMAL(9,6) NOT NULL,
  PRIMARY KEY (station_id),
  UNIQUE KEY uq_station_name (station_name)
);

CREATE TABLE `Line_Stations` (
  line_id INT NOT NULL,
  station_id INT NOT NULL,
  PRIMARY KEY (line_id, station_id),
  CONSTRAINT fk_ls_line FOREIGN KEY (line_id)
    REFERENCES `Lines`(line_id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ls_station FOREIGN KEY (station_id)
    REFERENCES `Stations`(station_id) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE `Alerts` (
  alert_id INT NOT NULL AUTO_INCREMENT,
  station_id INT NOT NULL,
  radius DECIMAL(10,2) NOT NULL DEFAULT 200.00,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  timestamp_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (alert_id),
  CONSTRAINT fk_alert_station FOREIGN KEY (station_id)
    REFERENCES `Stations`(station_id) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE INDEX idx_ls_line_id ON `Line_Stations`(line_id);
CREATE INDEX idx_alert_station ON `Alerts`(station_id);
CREATE INDEX idx_alert_status ON `Alerts`(status);