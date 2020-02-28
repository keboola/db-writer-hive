# https://github.com/big-data-europe/docker-hive
FROM bde2020/hadoop-base:2.0.0-hadoop2.7.4-java8

# Allow buildtime config of HIVE_VERSION
ARG HIVE_VERSION
ENV HIVE_VERSION=${HIVE_VERSION:-2.3.6}

ENV HIVE_HOME /opt/hive
ENV PATH $HIVE_HOME/bin:$PATH
ENV HADOOP_HOME /opt/hadoop-$HADOOP_VERSION

WORKDIR /opt

# Spark should be compiled with Hive to be able to use it
# hive-site.xml should be copied to $SPARK_HOME/conf folder

# Install utils
RUN (apt-get update || true) && \
    apt-get install -y wget procps && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Install Postgresql JDBC connector
RUN mkdir -p $HIVE_HOME/lib && \
    wget https://jdbc.postgresql.org/download/postgresql-9.4.1212.jar -O $HIVE_HOME/lib/postgresql-jdbc.jar

# Configuration
ADD conf/* $HIVE_HOME/conf/

COPY startup.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/startup.sh

COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

# Ports
EXPOSE 10000
EXPOSE 10002

# Entrypoint
ENTRYPOINT ["entrypoint.sh"]

# Init
COPY custom-init.sh  /usr/local/bin/custom-init.sh
CMD custom-init.sh

# Install Hive (last step, previous steps are same for all versions)
RUN	wget https://archive.apache.org/dist/hive/hive-$HIVE_VERSION/apache-hive-$HIVE_VERSION-bin.tar.gz && \
	tar --keep-old-files -xzvf apache-hive-$HIVE_VERSION-bin.tar.gz && \
	cp -r apache-hive-$HIVE_VERSION-bin/* hive/ && \
	rm -r apache-hive-$HIVE_VERSION-bin && \
	rm apache-hive-$HIVE_VERSION-bin.tar.gz
