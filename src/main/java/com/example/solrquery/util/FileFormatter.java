package com.example.solrquery.util;

import java.io.BufferedWriter;
import java.io.File;
import java.io.FileOutputStream;
import java.io.FileWriter;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.stream.Collectors;
import java.util.zip.ZipEntry;
import java.util.zip.ZipOutputStream;

import org.json.simple.JSONArray;
import org.json.simple.JSONObject;
import org.json.simple.parser.JSONParser;

import com.opencsv.CSVWriter;

public class FileFormatter {
	
	@SuppressWarnings("unchecked")
	public List<List<String>> JSONtoCSV (String json, Map<String, String> fieldList){
		
		List<List<String>> csv = new ArrayList<List<String>>();
		
		//Add header
		List<String> header = fieldList.values().stream().collect(Collectors.toList());
		csv.add(header);
		
		//Get all field values
		try {
            JSONParser parser = new JSONParser();
            Object resultObject = parser.parse(json);

            if (resultObject instanceof JSONObject) {
                JSONObject object = (JSONObject) resultObject;
                JSONObject response = (JSONObject) object.get("response");
                List<JSONObject> docs = (List<JSONObject>) response.get("docs"); 
                
                Set<String> fields = fieldList.keySet();
                
                for (JSONObject doc : docs){
                	List<String> line = new ArrayList<String>();
	                
                	for (String field : fields){
	                	Object attribute = doc.get(field);
	                	
	                	if (attribute == null){ //doc has no such field
	                		line.add("");
	                	}
	                	else{
		                	if (attribute instanceof JSONArray){ //field contains a list
		                		JSONArray items = (JSONArray) attribute;
		                		String list = new String(); 
		 		                		
			                	for (Object item : items){
			                		list += item + "||";
			                	}
			                	list = list.substring(0, list.length()-2); //remove last separator
		                		line.add(list);
		                	}
		                	else{
		                		line.add((String) attribute);
		                	}	
	                	}	
	                }
	                csv.add(line);
                }
            }

        } 
        catch (Exception e) {
        	e.printStackTrace();;
        }
		
		return csv;
	}
	
	private void compressFile (String inputfile){
		
		File file = new File(inputfile);
		File compressed = new File(inputfile.replace(".csv", ".zip"));
		
		try{
			ZipOutputStream writer = new ZipOutputStream(new FileOutputStream(compressed));
			ZipEntry entry = new ZipEntry(file.getName());
		
			writer.putNextEntry(entry);
			Files.copy(file.toPath(), writer);
			writer.closeEntry();
			writer.close();
		}
		catch(IOException e){  
			e.printStackTrace();	
		}
	}
	
	public void saveCSVFile(List<List<String>> records, char sep, String outputfile, boolean compress){
		
		try {
			BufferedWriter writer = new BufferedWriter(new FileWriter(outputfile + ".csv"));
	        CSVWriter csvWriter = new CSVWriter(writer, sep, CSVWriter.DEFAULT_QUOTE_CHARACTER, CSVWriter.DEFAULT_ESCAPE_CHARACTER, CSVWriter.DEFAULT_LINE_END);
	        
	        for (List<String> record : records) {
	        	String[] line = record.stream().toArray(String[]::new);
	            csvWriter.writeNext(line, false);
	        }
	        csvWriter.close();
	        writer.close(); 
	        
	        if (compress){
	        	compressFile(outputfile + ".csv");
	        	Files.delete(Paths.get(outputfile + ".csv")); //remove CSV file
	        }  
	    }
		catch(IOException e){  
			e.printStackTrace();
		}
	}
}
