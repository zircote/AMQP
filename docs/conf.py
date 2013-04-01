import sys, os
extensions = []
templates_path = ['_templates']
source_suffix = '.rst'
master_doc = 'index'
project = u'AMQP'
copyright = u'2012, Robert Allen et al'
version = '1.0.0'
release = '1.0.0'
exclude_patterns = ['_build']
from sphinx.highlighting import lexers
from pygments.lexers.web import PhpLexer
lexers['php'] = PhpLexer(startinline=True)
lexers['php-annotations'] = PhpLexer(startinline=True)
pygments_style = 'sphinx'
primary_domain = "php"
html_theme = 'default'
html_static_path = ['_static']
htmlhelp_basename = 'AMQPdoc'
latex_elements = {
}
latex_documents = [
  ('index', 'AMQP.tex', u'zircote/AMQP', u'zircote', 'manual'),
]
man_pages = [
    ('index', 'amqp', u'zircote/AMQP',[u'Robert Allen et al'], 1)
]
texinfo_documents = [
  ('index', 'AMQP', u'zircote/AMQP', u'Robert Allen et al', 'zircote/AMQP', 'A php AMQP library for php 5.3/5.4', 'Miscellaneous'),
]
