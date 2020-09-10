package org.lareferencia.services.vufindbulkdownloader;

import java.io.BufferedWriter;
import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.OutputStreamWriter;
import java.nio.charset.Charset;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.zip.ZipEntry;
import java.util.zip.ZipOutputStream;

import org.json.simple.JSONArray;
import org.json.simple.JSONObject;
import org.json.simple.parser.JSONParser;

import com.opencsv.CSVWriter;

public class FileUtils {
	
	@SuppressWarnings("unchecked")
	public List<List<String>> JSONtoCSV (String json, Map<String, String> fieldList, List<String> userFields){
		
		List<List<String>> csv = new ArrayList<List<String>>();
		Set<String> fields = fieldList.keySet();
		
		//Add header
		List<String> header = new ArrayList<String>();
		
		for (String field : fields){
			if (userFields.contains(field)){
				header.add(fieldList.get(field));
			}
		}
		csv.add(header);
		
		//Get all field values
		try {
            JSONParser parser = new JSONParser();
            Object resultObject = parser.parse(json);

            if (resultObject instanceof JSONObject) {
                JSONObject object = (JSONObject) resultObject;
                JSONObject response = (JSONObject) object.get("response");
                List<JSONObject> docs = (List<JSONObject>) response.get("docs"); 
                
                for (JSONObject doc : docs){
                	List<String> line = new ArrayList<String>();
	                
                	for (String field : fields){
                		if (userFields.contains(field)){ //user selected this field for export
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
	
	public void saveCSVFile(List<List<String>> records, char sep, String outputfile, String encoding, boolean compress){
		
		try {
			Charset charset = encoding.equals("UTF-8") ? StandardCharsets.UTF_8 : StandardCharsets.ISO_8859_1;
			BufferedWriter writer = new BufferedWriter(new OutputStreamWriter(
					new FileOutputStream(outputfile + ".csv"), charset));
	        CSVWriter csvWriter = new CSVWriter(writer, sep, CSVWriter.DEFAULT_QUOTE_CHARACTER, 
	        		CSVWriter.DEFAULT_ESCAPE_CHARACTER, CSVWriter.DEFAULT_LINE_END);
	        
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
