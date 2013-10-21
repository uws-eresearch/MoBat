'''
Created on 03/10/2013

@author: lloyd
'''
import os
import subprocess
import sys
import argparse
import csv
import tempfile
import Image
import glob
from os.path import expanduser
import jinja2
import zipfile
from cssutils.serialize import Out

def get_args(args):
    parser=argparse.ArgumentParser(description='Convert wordlist csv file to mods xml files.', prog='CSV Converter')
    parser.add_argument('-v','--verbose',action='store_true',dest='verbose',help='Increases messages being printed to stdout')
    #parser.add_argument('-c','--csv',action='store_true',dest='readcsv',help='Reads CSV file and converts to XML file with same name')
    parser.add_argument('-x','--xml',action='store_true',dest='toxml',help='Create mods xml files from the records in the CSV file')
    #parser.add_argument('-i','--inputfile',type=str,help='Name of file to be imported',required=True)
    parser.add_argument('inputfile',type=str,help='Name of the CSV file to be imported')
    parser.add_argument('template',type=str,help='Name of the xml template to be populated')
    parser.add_argument('outputfolder',type=str,help='(Optional) Output folder name',nargs='?')
    args = parser.parse_args()
    if not (args.toxml):
        parser.error('No action requested')
        return None
    if args.outputfolder is None:
        args.outputfolder = expanduser("~") + "/.adelta/ingest"
        #args.outputfolder = os.path.splitext(args.inputfile)[0] + '.xml'
        if not os.path.isdir(args.outputfolder):
            os.makedirs(args.outputfolder)
    return args

def main(argv):
    args = get_args(argv[1:])
    if args is None:
        return 1
    inputfile = open(args.inputfile, 'r')
    template_file = args.template
    output_folder = args.outputfolder
    reader = read_csv(inputfile)
    if args.verbose:
        print ('Verbose Selected')
    if args.toxml:
        if args.verbose:
            print ('Convert to XML Selected')
        generate_xml(reader, template_file, output_folder)

def read_csv(inputfile):
    return list(csv.reader(inputfile, delimiter='|'))

def generate_xml(reader, template_file, output_folder):
    templateLoader = jinja2.FileSystemLoader( searchpath="." )
    templateEnv = jinja2.Environment( loader=templateLoader )
    
    TEMPLATE_FILE = template_file
    template = templateEnv.get_template( TEMPLATE_FILE )
    
    i = 0
    for row in reader:
        if i > 0:
            id,title,author,collaborators,unique_id,description,language,date,publisher,platform,entry_author,url,isbn,translator,licence,date_modified = row
            names = collaborators.split(',')
            templateVars = {"title" : title,
                            "author" : author,
                            "collaborators" : names,
                            "unique_id" : unique_id,
                            "description" : description,
                            "language" : language,
                            "date" :date,
                            "publisher" :publisher,
                            "platform" :platform,
                            "entry_author" :entry_author,
                            "url" :url,
                            "isbn" : isbn,
                            "translator" :translator,
                            "licence" :licence,
                            "date_modified" :date_modified
                            }
            template.stream(templateVars).dump(output_folder + '/' + id + '.xml')
            
            #TODO thumbnail can be generated if requied
            image = None
            files = glob.glob(output_folder + '/' + id + '.*');
            for file in files:
                if os.path.splitext(file)[1].lower() in ('.jpg', '.jpeg', '.png', '.gif'):
                    image = file
            
            #Call php script from here to ingest data
            if image is not None:
                retCode = subprocess.call(['php', 'ingester.php', title, 
                                           output_folder + '/' + id + '.xml',  image, image])
                if retCode != 0:
                    return
            else:
                print ('No image file. Aborting...')
                return
        i+=1
        
    all_files = glob.glob(output_folder + '/*')
    for f in all_files:
        os.remove(f)      
        

if (__name__ == "__main__"):
    sys.exit(main(sys.argv))