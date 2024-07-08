/* -----------------------------------------------------------------------------
  - Project: RFID attendance system using NodeMCU
  - Author:  https://www.youtube.com/ElectronicsTechHaIs
  - Date:  6/03/2020
   -----------------------------------------------------------------------------
  This code was created by Electronics Tech channel for 
  the RFID attendance project with NodeMCU.
   ---------------------------------------------------------------------------*/
//*******************************libraries********************************
//RFID-----------------------------
#include <SPI.h>
#include <MFRC522.h>

//LCD------------------------------
#include <Wire.h> -----------------
#include <hd44780.h>                       // main hd44780 header
#include <hd44780ioClass/hd44780_I2Cexp.h> // i2c expander i/o class header
hd44780_I2Cexp lcd; // declare lcd object: auto locate & auto config expander chip
// LCD geometry
const int LCD_COLS = 16;
const int LCD_ROWS = 2;

//NodeMCU--------------------------
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecureBearSSL.h>
//************************************************************************
#define SS_PIN  D8  //D8
#define RST_PIN D3  //D3
//************************************************************************
MFRC522 mfrc522(SS_PIN, RST_PIN); // Create MFRC522 instance.
//************************************************************************
/* Set these to your desired credentials. */
const char *ssid = "SSID";
const char *password = "password";
const char* device_token  = "Device Token";
//************************************************************************
String URL = "https://<domain>/getdata.php"; //computer IP or the server domain
String getData, Link;
String OldCardID = "";
unsigned long previousMillis = 0;
//************************************************************************
void setup() {
  lcd.begin(LCD_COLS, LCD_ROWS);
  lcd.clear();
  Serial.begin(115200);
  SPI.begin();  // Init SPI bus
  mfrc522.PCD_Init(); // Init MFRC522 card
  //---------------------------------------------
  connectToWiFi();
}
//************************************************************************
void loop() {
  //check if there's a connection to Wi-Fi or not
  if(!WiFi.isConnected()){
    connectToWiFi();    //Retry to connect to Wi-Fi
  }
  //---------------------------------------------
  if (millis() - previousMillis >= 15000) {
    previousMillis = millis();
    OldCardID="";
  }
  delay(50);
  //---------------------------------------------
  //look for new card
  if ( ! mfrc522.PICC_IsNewCardPresent()) {
    return;//got to start of loop if there is no card present
  }
  // Select one of the cards
  if ( ! mfrc522.PICC_ReadCardSerial()) {
    return;//if read card serial(0) returns 1, the uid struct contians the ID of the read card.
  }
  String CardID ="";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    CardID += mfrc522.uid.uidByte[i];
  }
  //---------------------------------------------
  if( CardID == OldCardID ){
    return;
  }
  else{
    OldCardID = CardID;
  }
  //---------------------------------------------
//  Serial.println(CardID);
  SendCardID(CardID);
  delay(1000);
}
//************send the Card UID to the website*************
void SendCardID( String Card_uid ){
  Serial.println("Sending the Card ID");
  if(WiFi.isConnected()){
    //create an HTTPClient instance
    std::unique_ptr<BearSSL::WiFiClientSecure>client(new BearSSL::WiFiClientSecure);

    // Ignore SSL certificate validation
    client->setInsecure();
    
    HTTPClient https;    //Declare object of class HTTPClient
    //GET Data
    getData = "?card_uid=" + String(Card_uid) + "&device_token=" + String(device_token); // Add the Card ID to the GET array in order to send it
    //GET methode
    Link = URL + getData;
    https.begin(*client, Link); //initiate HTTP request   //Specify content-type header
    
    int httpCode = https.GET();   //Send the request
    String payload = https.getString();    //Get the response payload

//    Serial.println(Link);   //Print HTTP return code
    Serial.println(httpCode);   //Print HTTP return code
    Serial.println(Card_uid);     //Print Card ID
    lcd.clear();
    lcd.setCursor(0, 0);               // Set the cursor to the first column and first row
    lcd.print(payload); 
    delay(3000);
    Serial.println(payload);    //Print request response payload
    //Serial.println(Link);   //Print link for test
    if (httpCode == 200) {
      if (payload.substring(0, 5) == "login") {
        String user_name = payload.substring(5);
        Serial.println(user_name);
        lcd.clear();
        lcd.setCursor(0, 0);               // Set the cursor to the first column and first row
        lcd.print("Ingresso OK per"); 
        lcd.setCursor(0, 1);               // Set the cursor to the first column and first row
        lcd.print(user_name); 
        delay(5000);
        lcd.clear();
        lcd.setCursor(0, 0);               // Set the cursor to the first column and first row
        lcd.print("In attesa di una");
        lcd.setCursor(0, 1);               // Set the cursor to the first column and first row
        lcd.print("nuova scansione"); 

      }
      else if (payload.substring(0, 6) == "logout") {
        String user_name = payload.substring(6);
        Serial.println(user_name);
        lcd.clear();
        lcd.setCursor(0, 0);               // Set the cursor to the first column and first row
        lcd.print("Uscita OK per"); 
        lcd.setCursor(0, 1);               // Set the cursor to the first column and first row
        lcd.print(user_name); 
        delay(5000);
        lcd.clear();
        lcd.setCursor(0, 0);               // Set the cursor to the first column and first row
        lcd.print("In attesa di una"); 
        lcd.setCursor(0, 1);               // Set the cursor to the first column and first row
        lcd.print("nuova scansione"); 
        
      }
      else if (payload == "succesful") {

      }
      else if (payload == "available") {

      }
      delay(100);
      https.end();  //Close connection
    }
  }
}
//********************connect to the WiFi******************
void connectToWiFi(){
    WiFi.mode(WIFI_OFF);        //Prevents reconnection issue (taking too long to connect)
    delay(1000);
    WiFi.mode(WIFI_STA);
    Serial.print("Connecting to ");
    Serial.println(ssid);
    WiFi.begin(ssid, password);
    
    while (WiFi.status() != WL_CONNECTED) {
      delay(500);
      Serial.print(".");
    }
    Serial.println("");
    Serial.println("Connected");
  
    Serial.print("IP address: ");
    Serial.println(WiFi.localIP());  //IP address assigned to your ESP
    lcd.setCursor(0, 0);               // Set the cursor to the first column and first row
    lcd.print("    Connesso   ");
    delay(6000);
    lcd.setCursor(0, 0);               // Set the cursor to the first column and first row
    lcd.print("   Pronto per   "); 
    lcd.setCursor(0, 1);               // Set the cursor to the first column and first row
    lcd.print("nuova scansione");
    delay(1000);
}
//=======================================================================