'''
Created on 03/10/2013

@author: lloyd
'''
import os
import sys
import argparse
import csv
import tempfile
#import indent
#from xml.etree.ElementTree import parse, Element, SubElement, Comment, tostring
import jinja2
import zipfile

def get_args(args):
    parser=argparse.ArgumentParser(description='Convert wordlist csv files to xml.', prog='CSV Converter')
    parser.add_argument('-v','--verbose',action='store_true',dest='verbose',help='Increases messages being printed to stdout')
    parser.add_argument('-c','--csv',action='store_true',dest='readcsv',help='Reads CSV file and converts to XML file with same name')
    parser.add_argument('-x','--xml',action='store_true',dest='toxml',help='Convert CSV to XML with different name')
    #parser.add_argument('-i','--inputfile',type=str,help='Name of file to be imported',required=True)
    #parser.add_argument('-o','--outputfile',help='Output file name')
    parser.add_argument('inputfile',type=str,help='Name of file to be imported')
    parser.add_argument('template',type=str,help='Name of the xml template to be populated')
    parser.add_argument('image',type=str,help='Name of the image to associate with the record')
    parser.add_argument('outputfile',help='(optional) Output file name',nargs='?')
    args = parser.parse_args()
    if not (args.toxml or args.readcsv):
        parser.error('No action requested')
        return None
    if args.outputfile is None:
        args.outputfile = os.path.splitext(args.inputfile)[0] + '.xml'
    return args

def main(argv):
    args = get_args(argv[1:])
    if args is None:
        return 1
    inputfile = open(args.inputfile, 'r')
    template_file = args.template
    image_name = args.image
    #outputfile = args.outputfile
    reader = read_csv(inputfile)
    if args.verbose:
        print ('Verbose Selected')
    if args.toxml:
        if args.verbose:
            print ('Convert to XML Selected')
        generate_xml(reader, template_file, image_name)
    if args.readcsv:
        if args.verbose:
            print ('Reading CSV file')
    return 1 # you probably want to return 0 on success

def read_csv(inputfile):
    return list(csv.reader(inputfile))

def generate_xml(reader,template_file,image_name):
    
    templateLoader = jinja2.FileSystemLoader( searchpath="." )
    templateEnv = jinja2.Environment( loader=templateLoader )
    
    TEMPLATE_FILE = template_file
    template = templateEnv.get_template( TEMPLATE_FILE )
    
    dirpath = tempfile.mkdtemp()
    
    i = 0
    for row in reader:
        if i > 0:
            title,author,language = row
            templateVars = {"title" : title,
                            "author" : author,
                            "language" : language
                            }
            template.stream(templateVars).dump(dirpath + '/' + title + '.xml')
            with zipfile.ZipFile('batch.zip', 'a') as batch:
                batch.write(dirpath + '/' + title + '.xml', title + '.xml')
                batch.write(image_name, title + os.path.splitext(image_name)[1])
        i+=1
    
    
    
    
    
    
    
    #root = Element('mods')
    #root.set('version','1.0')
    #tree = ET(root)

    #head = SubElement(root, 'name')
    #head.set('type', 'personal')
    
    #tree = parse(template)
    #root = tree.getroot()
    
    #for child in root:
        #if child.tag == 'description':
            
            
        

    #description = SubElement(root,'namePart')
    #current_group = None
    #i = 0
    #for row in reader:
        #if i > 0:
            #x1,y1,z1 = row
            #if current_group is None or i != current_group.text:
                #current_group = SubElement(description, 'hole',{'hole_id':"%s"%i})

                #collar = SubElement (current_group, 'collar',{'':', '.join((x1,y1,z1))}),
                #toe = SubElement (current_group, 'toe',{'':', '.join((x2,y2,z2))})
                #cost = SubElement(current_group, 'cost',{'':cost})
        #i+=1
    #indent.indent(root)
    #tree.write(outfile)

if (__name__ == "__main__"):
    sys.exit(main(sys.argv))