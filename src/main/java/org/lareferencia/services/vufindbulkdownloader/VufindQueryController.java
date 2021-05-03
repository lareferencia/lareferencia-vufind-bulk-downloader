package org.lareferencia.services.vufindbulkdownloader;

import java.io.BufferedReader;
import java.io.File;
import java.io.IOException;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.MalformedURLException;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.time.Duration;
import java.time.ZoneId;
import java.time.ZonedDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import java.util.stream.Collectors;
import java.util.stream.Stream;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.annotation.PropertySource;
import org.springframework.core.io.FileSystemResource;
import org.springframework.http.HttpHeaders;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

@RestController
@PropertySource(value = "file:${bulk_downloader_home}/config/application.properties", encoding = "UTF-8")
public class VufindQueryController {
	
	@Value("${query.solr-server}")
	private String solrServer;
	
	@Value("${file.path}")
	private String filePath;
	
	@Value("${file.sep-char}")
	private char sep;
	
	@Value("${file.list-sep}")
	private String listSep;
	
	@Value("#{${file.header}}")
	private Map<String, String> fieldList;
	
	@Value("#{${file.agg-fields}}")
	private Map<String, List<String>> aggFields;
	
	@Value("${file.null-msg}")
	private String nullMsg;
	
	@Value("${file.null-msg-fields}")
	private List<String> nullMsgFields;
	
	@Value("${smtp.host}")
	private String smtpHost;
	
	@Value("${smtp.port}")
	private String smtpPort;
	
	@Value("${mail.sender}")
	private String sender;
	
	@Value("${mail.sender-pwd}")
	private String pwd;
	
	@Value("${mail.confirm-subject}")
	private String confSubject;
	
	@Value("${mail.ready-msg}")
	private String readyMsg;
	
	@Value("${mail.wait-msg-top}")
	private String waitMsgTop;
	
	@Value("${mail.wait-mg-bottom}")
	private String waitMsgBottom;
	
	@Value("${mail.link-subject}")
	private String linkSubject;
	
	@Value("${mail.link-msg-top}")
	private String linkMsgTop;
	
	@Value("${mail.link-msg-bottom}")
	private String linkMsgBottom;
	
	@Value("${time.short-record}")
	private Double shortRecTime;
	
	@Value("${time.long-record}")
	private Double longRecTime;
	
	@Value("${time.server-delay}")
	private Double delay;
	
	@Value("#{${time.units}}")
	private Map<String, String> timeUnits;
	
	@Value("${server.ip}")
	private String host;
	
	@Value("${server.port}")
	private String port;
	
	// Build the Solr query URL
	private String buildQueryUrl (String queryString){
		
		return solrServer + "/select?" + queryString;
	}
	
	// Build the URL for downloading the generated CSV file
	private String buildDownloadUrl (String fileName){

		return "http://" + host + ":" + port + "/query/download?fileName=" + fileName;
	}
	
	// Get the list of fields selected by the user for export
	private List<String> getUserFields (String queryString){
		
		List<String> fields = new ArrayList<String>();
		
		int listStart = queryString.lastIndexOf("&fl=") + 4;
		String list = queryString.substring(listStart, queryString.indexOf('&', listStart));
		fields = Stream.of(list.split(",")).collect(Collectors.toList());
		
		return fields;
	}
	
	// Estimate the time for creating the CSV file
	private String getTimeEstimate (int totalRecords, boolean hasAbstract){
		
		String estimate = new String();
		Double recTime = hasAbstract ? longRecTime : shortRecTime;
		long totalTime = (long)(totalRecords * recTime * delay);
		
		Duration timeLeft = Duration.ofMillis(totalTime);	
		long days = timeLeft.toDays();
		timeLeft = timeLeft.minusDays(days);
		long hours = timeLeft.toHours();
		timeLeft = timeLeft.minusHours(hours);
		long minutes = timeLeft.toMinutes();
        
		if (days > 0) {
        	estimate = days + " " + timeUnits.get("day") + ", " + 
        				hours + " " + timeUnits.get("hour") + " " + 
						timeUnits.get("conjunction") + " " + 
						minutes + " " + timeUnits.get("minute");    
        }
        else if (hours > 0){
        	estimate = hours + " " + timeUnits.get("hour") + " " + 
						timeUnits.get("conjunction") + " " + 
						minutes + " " + timeUnits.get("minute");
        }
        else {
        	estimate = minutes + " " + timeUnits.get("minute");
        }
		
		return estimate;
	}
	
	// Query Solr to get the data and create a CSV file from it
	private void createFile (String queryString, String outputFile, String encoding){
		
		StringBuffer content = new StringBuffer();
		
		try {	
			URL url = new URL(buildQueryUrl(queryString));
			HttpURLConnection con = (HttpURLConnection) url.openConnection();
			con.setRequestMethod("GET");
			
			BufferedReader in = new BufferedReader(new InputStreamReader(con.getInputStream(), StandardCharsets.UTF_8));
			String inputLine;
			
			//Read the response
			while ((inputLine = in.readLine()) != null) {
				content.append(inputLine);
			}
			
			in.close();
			con.disconnect();
			
		} catch (MalformedURLException e) {
			e.printStackTrace();
		} catch (IOException e) {
			e.printStackTrace();
		}
		
		//Convert to CSV and save to compressed file		
		FileUtils f = new FileUtils();
		List<String> userFields = getUserFields(queryString);
		List<List<String>> csv = f.JSONtoCSV(content.toString(), fieldList, userFields, aggFields, listSep, nullMsg, nullMsgFields);
		f.saveCSVFile(csv, sep, outputFile, encoding, true); //always compress CSV file
	}
	
	@RequestMapping("/existFile")
	public boolean fileExists(@RequestParam(required = true) String queryString){
		
		String date = ZonedDateTime.now(ZoneId.systemDefault()).format(DateTimeFormatter.ofPattern("uuuuMMdd"));
		String sufix = queryString + date;
		String fileName = "search_result-" + String.valueOf(sufix.hashCode());
		String outputFile = filePath + fileName;
		
		if (Files.exists(Paths.get(outputFile + ".zip"))){
			return true;
		}
		else{
			return false;
		}
	}
	
	@RequestMapping("/query")
	public String executeQuery(@RequestParam(required = true) String queryString, 
			@RequestParam(required = true) String download,
			@RequestParam(required = true) String totalRecords,
			@RequestParam(required = true) String hasAbstract,
			@RequestParam(required = true) String encoding,
			@RequestParam(required = true) String userEmail) {
		
		boolean isDownload = Boolean.parseBoolean(download);
		boolean includeAbstract = Boolean.parseBoolean(hasAbstract);
		int numRecords = Integer.valueOf(totalRecords);
		
		String date = ZonedDateTime.now(ZoneId.systemDefault()).format(DateTimeFormatter.ofPattern("uuuuMMdd"));
		String sufix = queryString + date;
		String fileName = "search_result-" + String.valueOf(sufix.hashCode());
		String outputFile = filePath + fileName;
		String downloadUrl = buildDownloadUrl(fileName + ".zip");
		
		Mailer mailer = new Mailer(smtpHost, smtpPort, sender, pwd);
		
		if (isDownload || Files.exists(Paths.get(outputFile + ".zip"))){
			//User will be able to download the file immediately
			
			//Only creates the CSV file if a file created from the same query does not already exist
			if (Files.notExists(Paths.get(outputFile + ".zip"))){
				createFile(queryString, outputFile, encoding); 
			}
			
			//Send a confirmation email
			mailer.sendMail(sender, userEmail, confSubject, readyMsg);
			
			return downloadUrl;
		}
		else{
			//Download URL will be sent to user by email later
			
			//First send an email acknowledging the request was received
			String waitMsg = waitMsgTop + " " + getTimeEstimate(numRecords, includeAbstract) + " " + waitMsgBottom;
			mailer.sendMail(sender, userEmail, confSubject, waitMsg);
			
			//Create the CSV file
			createFile(queryString, outputFile, encoding);
			
			//Send download URL by email
			String linkMsg = linkMsgTop + " " + downloadUrl + linkMsgBottom;
			mailer.sendMail(sender, userEmail, linkSubject, linkMsg);
			
			return null;
		}
	}
	
	@RequestMapping("/query/download")
	public ResponseEntity<FileSystemResource> downloadFile(@RequestParam(required = true) String fileName) throws IOException {
	 
		File file = new File(filePath + fileName);
		FileSystemResource resource = new FileSystemResource(file);
	 
	    return ResponseEntity.ok()
	    		.header(HttpHeaders.CONTENT_DISPOSITION, "attachment;filename=" + file.getName())
	    		.contentType(MediaType.parseMediaType("application/zip"))
	    		.contentLength(file.length())
	    		.body(resource);
	}

}
