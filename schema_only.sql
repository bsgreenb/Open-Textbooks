-- phpMyAdmin SQL Dump
-- version 3.3.9.2
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Feb 11, 2012 at 07:17 PM
-- Server version: 5.5.9
-- PHP Version: 5.3.6

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `textyard`
--

-- --------------------------------------------------------

--
-- Table structure for table `Bookstores`
--

CREATE TABLE `Bookstores` (
  `Bookstore_ID` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `Bookstore_Type_ID` tinyint(10) unsigned NOT NULL,
  `Storefront_URL` varchar(2043) NOT NULL COMMENT 'URL used when we want to link students to the bookstore (typically because we don''t have a given book)',
  `Fetch_URL` varchar(2043) NOT NULL COMMENT 'URL used to fetch Class-Items data from the bookstore',
  `Store_Value` varchar(100) DEFAULT NULL COMMENT 'Store identifier used in BN and Follet bookstores',
  `Follett_HEOA_Store_Value` varchar(100) DEFAULT NULL COMMENT 'Follett_HEOA_Store_Value',
  `Multiple_Campuses` enum('Y','N') DEFAULT NULL,
  PRIMARY KEY (`Bookstore_ID`),
  KEY `Bookstore_Type_ID` (`Bookstore_Type_ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1280 ;

-- --------------------------------------------------------

--
-- Table structure for table `Bookstore_Types`
--

CREATE TABLE `Bookstore_Types` (
  `Bookstore_Type_ID` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `Bookstore_Type_Name` varchar(40) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`Bookstore_Type_ID`),
  UNIQUE KEY `Bookstore_Type_Name` (`Bookstore_Type_Name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=7 ;

-- --------------------------------------------------------

--
-- Table structure for table `Campuses`
--

CREATE TABLE `Campuses` (
  `Campus_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Bookstore_ID` int(10) unsigned NOT NULL,
  `Campus_Value` varchar(100) DEFAULT NULL COMMENT 'Some Follett schools require this (e.g. Ivy Techs), also BN always requires it.',
  `Program_Value` varchar(100) DEFAULT NULL COMMENT 'Identifier for "Programs" used in Follets system.  E.g. there could be a program for college and a program for a HS.  Only used in Follett systems',
  `Location` tinytext NOT NULL,
  `Added_TimeStamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'when the campuses was added to db',
  `Enabled` enum('Y','N') NOT NULL,
  PRIMARY KEY (`Campus_ID`),
  KEY `Bookstore_ID` (`Bookstore_ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1304 ;

-- --------------------------------------------------------

--
-- Table structure for table `Campus_Names`
--

CREATE TABLE `Campus_Names` (
  `Campus_ID` int(10) unsigned NOT NULL,
  `Campus_Name` varchar(255) NOT NULL,
  `Is_Primary` enum('Y','N') CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`Campus_ID`,`Campus_Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `Classes_Cache`
--

CREATE TABLE `Classes_Cache` (
  `Class_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Course_ID` int(10) unsigned NOT NULL,
  `Class_Code` varchar(50) NOT NULL COMMENT 'Class_Code known/shown to students',
  `Instructor` varchar(50) DEFAULT NULL,
  `Class_Value` varchar(100) NOT NULL COMMENT 'value sent to the Bookstore',
  `Cache_TimeStamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Class_ID`),
  UNIQUE KEY `Course_Class` (`Course_ID`,`Class_Value`),
  KEY `Course_ID` (`Course_ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=46444 ;

-- --------------------------------------------------------

--
-- Table structure for table `Class_Items_Cache`
--

CREATE TABLE `Class_Items_Cache` (
  `Class_Items_Cache_ID` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Actually used in sorting, to tell us the importance of Books (based on the order  in which they were received when fetched from bookstore',
  `Class_ID` int(10) unsigned NOT NULL,
  `Item_ID` int(10) unsigned DEFAULT NULL,
  `Bookstore_Price` decimal(5,2) DEFAULT NULL COMMENT 'We store here rather than Items, because you could have the same Item being listed at different prices depending  on the bookstore.',
  `New_Price` decimal(5,2) unsigned DEFAULT NULL,
  `Used_Price` decimal(5,2) unsigned DEFAULT NULL,
  `New_Rental_Price` decimal(5,2) unsigned DEFAULT NULL,
  `Used_Rental_Price` decimal(5,2) unsigned DEFAULT NULL,
  `Necessity` varchar(50) DEFAULT NULL COMMENT 'e.g. "Required", "Recommended", etc.  We grab from Bookstore',
  `Comments` varchar(1000) DEFAULT NULL,
  `Cache_TimeStamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Class_Items_Cache_ID`),
  UNIQUE KEY `Class_Item` (`Class_ID`,`Item_ID`),
  KEY `Item_ID` (`Item_ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=68 ;

-- --------------------------------------------------------

--
-- Table structure for table `Courses_Cache`
--

CREATE TABLE `Courses_Cache` (
  `Course_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Department_ID` int(10) unsigned NOT NULL,
  `Course_Code` varchar(100) NOT NULL COMMENT 'Course_Code known/shown to students',
  `Course_Value` varchar(100) DEFAULT NULL COMMENT 'value sent to the Bookstore.  It can be NULL because of the Neebo situation where specific courses are never sent to the bookstore..',
  `Cache_TimeStamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Course_ID`),
  UNIQUE KEY `Department_Course` (`Department_ID`,`Course_Value`),
  KEY `Department_ID` (`Department_ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=115202 ;

-- --------------------------------------------------------

--
-- Table structure for table `Departments_Cache`
--

CREATE TABLE `Departments_Cache` (
  `Department_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Division_ID` int(10) unsigned NOT NULL,
  `Department_Code` varchar(50) NOT NULL COMMENT 'Department_Code shown/known to students',
  `Department_Value` varchar(100) NOT NULL COMMENT 'value sent to the Bookstore',
  `Cache_TimeStamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Department_ID`),
  UNIQUE KEY `Term_Department` (`Division_ID`,`Department_Code`),
  KEY `Term_ID` (`Division_ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=158764 ;

-- --------------------------------------------------------

--
-- Table structure for table `Divisions_Cache`
--

CREATE TABLE `Divisions_Cache` (
  `Division_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Term_ID` int(10) unsigned NOT NULL,
  `Division_Name` varchar(100) DEFAULT NULL COMMENT 'It can be NULL because sometimes we have placeholders',
  `Division_Value` varchar(100) DEFAULT NULL COMMENT 'It can be NULL because sometimes we have placeholders',
  `Cache_TimeStamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Division_ID`),
  KEY `Term_ID` (`Term_ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2076 ;

-- --------------------------------------------------------

--
-- Table structure for table `Items`
--

CREATE TABLE `Items` (
  `Item_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ISBN` char(13) DEFAULT NULL,
  `Title` varchar(255) NOT NULL,
  `Edition` varchar(20) NOT NULL DEFAULT '''''',
  `Authors` varchar(255) NOT NULL DEFAULT '''''',
  `Year` year(4) NOT NULL DEFAULT '0000',
  `Publisher` varchar(50) NOT NULL DEFAULT '''''',
  PRIMARY KEY (`Item_ID`),
  UNIQUE KEY `Unique_Item` (`Title`,`Edition`,`Authors`,`Year`,`Publisher`),
  UNIQUE KEY `ISBN` (`ISBN`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='Items includes, but is not limited to isbn-having books.' AUTO_INCREMENT=33772 ;

-- --------------------------------------------------------

--
-- Table structure for table `Terms_Cache`
--

CREATE TABLE `Terms_Cache` (
  `Term_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Campus_ID` int(10) unsigned NOT NULL,
  `Term_Name` varchar(50) NOT NULL COMMENT 'Shown in drop down',
  `Term_Value` varchar(100) NOT NULL COMMENT 'Sent to Bookstore',
  `Follett_HEOA_Term_Value` varchar(100) DEFAULT NULL COMMENT 'Value sent to the Follett HEOA page',
  `Cache_TimeStamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Term_ID`),
  UNIQUE KEY `Campus_Term` (`Campus_ID`,`Term_Value`),
  KEY `Campus_ID` (`Campus_ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=5873 ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `Campus_Names`
--
ALTER TABLE `Campus_Names`
  ADD CONSTRAINT `Campus_Names_ibfk_1` FOREIGN KEY (`Campus_ID`) REFERENCES `Campuses` (`Campus_ID`);

--
-- Constraints for table `Classes_Cache`
--
ALTER TABLE `Classes_Cache`
  ADD CONSTRAINT `Classes_Cache_ibfk_1` FOREIGN KEY (`Course_ID`) REFERENCES `Courses_Cache` (`Course_ID`);
