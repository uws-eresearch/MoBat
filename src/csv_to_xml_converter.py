# -*- coding: utf-8 -*-
'''
Created on 03/10/2013

@author: lloyd
'''
import os
import sys
import datetime
import argparse
import csv
from os.path import expanduser
import jinja2
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
            #id,title,image_dir,author,collaborators,unique_id,description,language,date,publisher,platform,entry_author,url,isbn,translator,licence,date_modified = row
            #id,author,title,image_dir,date,platform,publisher,collaborators,url,description,language,entry_author,isbn,translator,licence = row
            id,author,title,image_dir,critical_work,media,date,platform,genre,tags,collaborators,url,description,entry_author,publisher,language,translator,isbn,licence,date_modified = row
            author_names = author.split(';')
            collab_names = collaborators.split(',')
            date_modified = datetime.date.today().strftime("%B %d, %Y")
            templateVars = {"title" : title,
                            "image_dir" : image_dir,
                            "authors" : author_names,
                            "collaborators" : collab_names,
                            #"unique_id" : unique_id,
                            "description" : description,
                            "language" : language,
                            "date" :date,
                            "publisher" :publisher,
                            "platform" :platform,
                            "entry_author" :entry_author,
                            "url" :url,
                            "isbn" : isbn,
                            "translator" :translator,
                            "licence" :licence
                            }
            template.stream(templateVars).dump(output_folder + '/' + id + '.xml', 'utf-8')
            
        i+=1
        

if (__name__ == "__main__"):
    sys.exit(main(sys.argv))