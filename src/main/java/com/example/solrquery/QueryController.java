package com.example.solrquery;

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
import java.time.ZoneId;
import java.time.ZonedDateTime;
import java.time.format.DateTimeFormatter;
import java.util.List;
import java.util.Map;
import java.util.Set;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.core.io.FileSystemResource;
import org.springframework.http.HttpHeaders;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

import com.example.solrquery.util.FileFormatter;

@RestController
public class QueryController {
	
	@Value("${query.solr-server}")
	private String solrServer;
	
	@Value("#{${query.fields}}")
	private Map<String, String> fieldList;
	
	@Value("${file.path}")
	private String filePath;
	
	@Value("${file.content.separator}")
	private char sep;
	
	@Value("${server.ip}")
	private String host;
	
	@Value("${server.port}")
	private String port;
	
	private String buildQueryUrl (String queryString){
		
		Set<String> keys = fieldList.keySet();
		String fl =	String.join(",", keys);
		
		return solrServer + "/select?" + queryString + "&fl=" + fl ;
	}
	
	private String buildDownloadUrl (String fileName){

		return "http://" + host + ":" + port + "/query/download?fileName=" + fileName;
	}
	
	@RequestMapping("/query")
	public String executeQuery(@RequestParam(required = true) String queryString) {
		
		String date = ZonedDateTime.now(ZoneId.systemDefault()).format(DateTimeFormatter.ofPattern("uuuuMMdd"));
		String sufix = queryString + date;
		String fileName = "search_result-" + String.valueOf(sufix.hashCode());
		String outputFile = filePath + fileName;
		
		//Only creates the CSV file if a file created from the same query does not already exist
		if (Files.notExists(Paths.get(outputFile + ".zip"))){
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
			FileFormatter f = new FileFormatter();
			List<List<String>> csv = f.JSONtoCSV(content.toString(), fieldList);
			f.saveCSVFile(csv, sep, outputFile, true); //always compress CSV file 
		}
			
		return buildDownloadUrl(fileName + ".zip");		
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
