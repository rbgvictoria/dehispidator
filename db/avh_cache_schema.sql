--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: biocasediagnostic; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE biocasediagnostic (
    "biocaseDiagnosticID" integer NOT NULL,
    "biocaseResponseID" integer,
    severity character varying(16),
    text character varying(255)
);


--
-- Name: biocasediagnostic_biocaseDiagnosticID_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE "biocasediagnostic_biocaseDiagnosticID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: biocasediagnostic_biocaseDiagnosticID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE "biocasediagnostic_biocaseDiagnosticID_seq" OWNED BY biocasediagnostic."biocaseDiagnosticID";


--
-- Name: biocaseresponse; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE biocaseresponse (
    "biocaseResponseID" integer NOT NULL,
    "resourceID" integer,
    "xmlSchema" character varying(64),
    source character varying(128),
    "sendTime" character varying(64),
    "recordCount" integer,
    "recordDropped" integer,
    "recordStart" integer,
    "totalSearchHits" integer
);


--
-- Name: biocaseresponse_biocaseResponseID_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE "biocaseresponse_biocaseResponseID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: biocaseresponse_biocaseResponseID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE "biocaseresponse_biocaseResponseID_seq" OWNED BY biocaseresponse."biocaseResponseID";


--
-- Name: core; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE core (
    "CoreID" integer NOT NULL,
    "institutionCode" character varying(6) NOT NULL,
    "collectionCode" character varying(6) NOT NULL,
    "catalogNumber" character varying(32) NOT NULL,
    "occurrenceID" character varying(64),
    "basisOfRecord" character varying(32) DEFAULT 'PreservedSpecimen'::character varying,
    preparations character varying(255),
    modified timestamp without time zone,
    "recordedBy" character varying(512),
    "AdditionalCollectors" character varying(512),
    "collectorsFieldNumber" character varying(64),
    "eventDate" character varying(32),
    "verbatimEventDate" character varying(64),
    country character varying(64),
    "countryCode" character varying(2),
    "stateProvince" character varying(64),
    locality text,
    "GeneralisedLocality" character varying(128),
    "NearNamedPlaceRelationship" character varying(32),
    "decimalLatitude" double precision,
    "verbatimLatitude" character varying(64),
    "decimalLongitude" double precision,
    "verbatimLongitude" character varying(64),
    "verbatimCoordinates" character varying(128),
    "geodeticDatum" character varying(64),
    "coordinateUncertaintyInMeters" double precision,
    "georeferencedBy" character varying(64),
    "georeferencedProtocol" character varying(64),
    "minimumElevationInMeters" double precision,
    "maximumElevationInMeters" double precision,
    "verbatimElevation" character varying(64),
    "minimumDepthInMeters" double precision,
    "maximumDepthInMeters" double precision,
    "verbatimDepth" character varying(64),
    habitat text,
    "occurrenceRemarks" text,
    "scientificName" character varying(256),
    kingdom character varying(64),
    phylum character varying(64),
    class character varying(64),
    "order" character varying(64),
    family character varying(64),
    genus character varying(64),
    "specificEpithet" character varying(128),
    "taxonRank" character varying(32),
    "infraspecificEpithet" character varying(64),
    "CultivarName" character varying(64),
    "scientificNameAuthorship" character varying(128),
    "nomenclaturalStatus" character varying(64),
    "identificationQualifier" character varying(32),
    "IdentificationQualifierInsertionPoint" smallint,
    "DwCIdentificationQualifier" character varying(256),
    "ScientificNameAddendum" character varying(32),
    "DeterminerRole" character varying(32),
    "identifiedBy" character varying(128),
    "dateIdentified" character varying(32),
    "VerbatimIdentificationDate" character varying(64),
    "identificationRemarks" text,
    "typeStatus" character varying(32),
    "TypifiedName" character varying(255),
    "DoubtfulFlag" character varying(32),
    "Verifier" character varying(128),
    "VerificationDate" character varying(32),
    "VerificationNotes" text,
    "DwCTypeStatus" text,
    "ExHerb" character varying(128),
    "ExHerbCatalogueNumber" character varying(32),
    "DuplicatesDistributedTo" character varying(256),
    "LoanDate" date,
    "LoanDestination" character varying(64),
    "LoanForBotanist" character varying(256),
    "LoanIdentifier" character varying(32),
    "LoanReturnDate" date,
    "Australian Herbarium Region" character varying(64),
    "IBRARegion" character varying(64),
    "IBRASubregion" character varying(64),
    "Phenology" character varying(128),
    "CultivatedOccurrence" character varying(64),
    "NaturalOccurrence" character varying(64),
    "establishmentMeans" character varying(128),
    "TimeLoaded" timestamp without time zone DEFAULT now(),
    "Quarantine" smallint,
    "previousIdentifications" text,
    "coordinatePrecision" double precision,
    "verbatimCoordinateSystem" character varying(64),
    "verbatimSRS" character varying(64),
    "locationRemarks" text,
    "associatedTaxa" text,
    "Topography" text,
    "Aspect" character varying(10),
    "HispidHabitat" text,
    "Substrate" character varying(128),
    "Soil" character varying(128),
    "Vegetation" text,
    "abcd_AssemblageID" character varying(32),
    "abcd_UnitNotes" text,
    "hispid_Frequency" character varying(64),
    "hispid_Voucher" character varying(32),
    "hispid_Habit" text,
    county character varying(128),
    continent character varying(32),
    subclass character varying(64)
);


--
-- Name: core_CoreID_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE "core_CoreID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: core_CoreID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE "core_CoreID_seq" OWNED BY core."CoreID";


--
-- Name: dberrors; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE dberrors (
    "dbErrorsID" integer NOT NULL,
    "timestamp" timestamp without time zone DEFAULT now(),
    "table" character varying(16),
    "catalogNumber" character varying(32),
    "sqlStateErrorCode" character varying(16),
    "driverSpecificErrorCode" character varying(16),
    "driverSpecificErrorMessage" text
);


--
-- Name: dberrors_dbErrorsID_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE "dberrors_dbErrorsID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dberrors_dbErrorsID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE "dberrors_dbErrorsID_seq" OWNED BY dberrors."dbErrorsID";


--
-- Name: determinationhistory; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE determinationhistory (
    "DeterminationHistoryID" integer NOT NULL,
    "CoreID" integer NOT NULL,
    "scientificName" character varying(256),
    kingdom character varying(64),
    phylum character varying(64),
    class character varying(64),
    "order" character varying(64),
    family character varying(64),
    genus character varying(64),
    "specificEpithet" character varying(64),
    "taxonRank" character varying(32),
    "infraspecificEpithet" character varying(64),
    "CultivarName" character varying(64),
    "scientificNameAuthorship" character varying(128),
    "nomenclaturalStatus" character varying(64),
    "identificationQualifier" character varying(32),
    "IdentificationQualifierInsertionPoint" smallint,
    "DwCIdentificationQualifier" character varying(256),
    "ScientificNameAddendum" character varying(32),
    "DeterminerRole" character varying(32),
    "identifiedBy" character varying(128),
    "dateIdentified" character varying(32),
    "VerbatimIdentificationDate" character varying(64),
    "identificationRemarks" text
);


--
-- Name: determinationhistory_DeterminationHistoryID_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE "determinationhistory_DeterminationHistoryID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: determinationhistory_DeterminationHistoryID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE "determinationhistory_DeterminationHistoryID_seq" OWNED BY determinationhistory."DeterminationHistoryID";


--
-- Name: resource; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE resource (
    resource_id integer NOT NULL,
    resource_name character varying(64) NOT NULL,
    url character varying(128) NOT NULL,
    dsa character varying(32) DEFAULT NULL::character varying,
    resource_schema character varying(128) NOT NULL,
    resource_code character varying(32) DEFAULT NULL::character varying,
    incremental_update_flag smallint NOT NULL,
    date_last_queried timestamp without time zone
);


--
-- Name: resource_resource_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE resource_resource_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: resource_resource_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE resource_resource_id_seq OWNED BY resource.resource_id;


--
-- Name: biocaseDiagnosticID; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY biocasediagnostic ALTER COLUMN "biocaseDiagnosticID" SET DEFAULT nextval('"biocasediagnostic_biocaseDiagnosticID_seq"'::regclass);


--
-- Name: biocaseResponseID; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY biocaseresponse ALTER COLUMN "biocaseResponseID" SET DEFAULT nextval('"biocaseresponse_biocaseResponseID_seq"'::regclass);


--
-- Name: CoreID; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY core ALTER COLUMN "CoreID" SET DEFAULT nextval('"core_CoreID_seq"'::regclass);


--
-- Name: dbErrorsID; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY dberrors ALTER COLUMN "dbErrorsID" SET DEFAULT nextval('"dberrors_dbErrorsID_seq"'::regclass);


--
-- Name: DeterminationHistoryID; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY determinationhistory ALTER COLUMN "DeterminationHistoryID" SET DEFAULT nextval('"determinationhistory_DeterminationHistoryID_seq"'::regclass);


--
-- Name: resource_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY resource ALTER COLUMN resource_id SET DEFAULT nextval('resource_resource_id_seq'::regclass);


--
-- Name: core_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY core
    ADD CONSTRAINT core_pkey PRIMARY KEY ("CoreID");


--
-- Name: determinationhistory_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY determinationhistory
    ADD CONSTRAINT determinationhistory_pkey PRIMARY KEY ("DeterminationHistoryID");


--
-- Name: resource_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY resource
    ADD CONSTRAINT resource_pkey PRIMARY KEY (resource_id);


--
-- Name: biocasediagnostic_biocaseresponseid_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX biocasediagnostic_biocaseresponseid_index ON biocasediagnostic USING btree ("biocaseResponseID");


--
-- Name: biocasediagnostic_severity_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX biocasediagnostic_severity_index ON biocasediagnostic USING btree (severity);


--
-- Name: biocasediagnostic_text_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX biocasediagnostic_text_index ON biocasediagnostic USING btree (text);


--
-- Name: biocaseresponse_resourceid_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX biocaseresponse_resourceid_index ON biocaseresponse USING btree ("resourceID");


--
-- Name: core_catalognumber_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX core_catalognumber_index ON core USING btree ("catalogNumber");


--
-- Name: core_catalognumber_unique; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX core_catalognumber_unique ON core USING btree ("collectionCode", "catalogNumber");


--
-- Name: core_collectioncode_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX core_collectioncode_index ON core USING btree ("collectionCode");


--
-- Name: core_decimallatitude_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX core_decimallatitude_index ON core USING btree ("decimalLatitude");


--
-- Name: core_decimallongitude_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX core_decimallongitude_index ON core USING btree ("decimalLongitude");


--
-- Name: core_institutioncode_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX core_institutioncode_index ON core USING btree ("institutionCode");


--
-- Name: core_modified_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX core_modified_index ON core USING btree (modified);


--
-- Name: core_quarantine_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX core_quarantine_index ON core USING btree ("Quarantine");


--
-- Name: core_timeloaded_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX core_timeloaded_index ON core USING btree ("TimeLoaded");


--
-- Name: dberrors_catalognumber_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX dberrors_catalognumber_index ON dberrors USING btree ("catalogNumber");


--
-- Name: dberrors_timestamp_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX dberrors_timestamp_index ON dberrors USING btree ("timestamp");


--
-- Name: determinationhistory_coreid_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX determinationhistory_coreid_index ON determinationhistory USING btree ("CoreID");


--
-- Name: resource_resource_name_unique_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX resource_resource_name_unique_index ON resource USING btree (resource_name);


--
-- Name: public; Type: ACL; Schema: -; Owner: -
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

