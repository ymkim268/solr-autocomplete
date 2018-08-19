import java.io.File;
import java.io.FileInputStream;
import java.io.IOException;
import java.io.PrintWriter;

import org.apache.tika.exception.TikaException;
import org.apache.tika.metadata.Metadata;
import org.apache.tika.parser.ParseContext;
import org.apache.tika.parser.html.HtmlParser;
import org.apache.tika.sax.BodyContentHandler;

import org.xml.sax.SAXException;

public class HtmlParse {
    
   public static void main(final String[] args) throws IOException,SAXException, TikaException {
       
       
       String fileDir = "/Users/ymkim/Desktop/cs572-hw4-workspace/hw4-java/HTML_files";
       String outputPath = "/Users/ymkim/Desktop/cs572-hw5-workspace/output.txt";
       
       File dir = new File(fileDir);
       File out = new File(outputPath);
       PrintWriter pw = new PrintWriter(out);
       
       int writeLimit = -1; // disable write limit
       
       int i = 0;
       for(File file : dir.listFiles()) {
           String fileName = file.getName();
           
           if(fileName.contains("html")) {
               i++;
               
               //detecting the file type
               BodyContentHandler handler = new BodyContentHandler(writeLimit);
               Metadata metadata = new Metadata();
               FileInputStream inputstream = new FileInputStream(new File(fileDir + "/" + fileName));
               ParseContext pcontext = new ParseContext();
               
               //Html parser 
               HtmlParser htmlparser = new HtmlParser();
               htmlparser.parse(inputstream, handler, metadata,pcontext);
               
               String plainText = handler.toString();
               plainText = plainText.replaceAll("(\\s)+", " ");
               
               pw.println(plainText);
               // System.out.println("Contents of the document" + "[" + i + "]:" + plainText);
               
           }
       }
       pw.flush();
       pw.close();
       
       System.out.println("num of html files: " + i);


      
      
      
      
      
      
      
      
   }
}